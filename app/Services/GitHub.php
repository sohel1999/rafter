<?php

namespace App\Services;

use App\SourceProvider;
use GuzzleHttp\Client;
use App\Contracts\SourceProviderClient;
use App\Deployment;
use Exception;
use GuzzleHttp\Exception\ClientException;

class GitHub implements SourceProviderClient
{
    protected $source;

    public function __construct(SourceProvider $source) {
        $this->source = $source;
    }

    /**
     * Determine if the source control credentials are valid.
     *
     * @return bool
     */
    public function valid()
    {
        try {
            $response = $this->request('user/repos');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate the given repository and branch are valid.
     *
     * @param  string  $repository
     * @param  string  $branch
     * @return bool
     */
    public function validRepository($repository, $branch)
    {
        if (empty($repository)) {
            return false;
        }

        try {
            $response = $this->request("repos/{$repository}/branches");
        } catch (ClientException $e) {
            return false;
        }

        if (empty($branch)) {
            return true;
        }

        return collect($response)->contains(function ($b) use ($branch) {
            return $b['name'] === $branch;
        });
    }

    /**
     * Validate the given repository and commit hash are valid.
     *
     * @param  string  $repository
     * @param  string  $hash
     * @return bool
     */
    public function validCommit($repository, $hash)
    {
        if (empty($repository) || empty($hash)) {
            return false;
        }

        try {
            $response = $this->request("repos/{$repository}/commits/{$hash}");
        } catch (ClientException $e) {
            return false;
        }

        return $response['sha'] === $hash;
    }

    /**
     * Get the latest commit hash for the given repository and branch.
     *
     * @param  string  $repository
     * @param  string  $branch
     * @return string
     */
    public function latestHashFor($repository, $branch)
    {
        return $this->request("repos/{$repository}/commits?sha={$branch}&per_page=1")[0]['sha'];
    }

    /**
     * Get the tarball URL for the given deployment.
     *
     * @param  \App\Deployment  $deployment
     * @return string
     */
    public function tarballUrl(Deployment $deployment)
    {
        return sprintf(
            'https://api.github.com/repos/%s/tarball/%s?access_token=%s',
            $deployment->repository(),
            $deployment->commit_hash,
            $this->token()
        );
    }

    /**
     * Get the commit hash from the given hook payload.
     *
     * @param  array  $payload
     * @return string|null
     */
    public function extractCommitFromHookPayload(array $payload)
    {
        return $payload['head_commit']['id'] ?? null;
    }

    /**
     * Get an access token from a code during a user's OAuth flow.
     */
    public function exchangeCodeForAccessToken($code)
    {
        $response = $this->request(
            "login/oauth/access_token",
            "POST",
            [
                "client_id" => config('services.github.client_id'),
                "client_secret" => config('services.github.client_secret'),
                "code" => $code,
            ],
            true,
            "https://github.com/"
        );

        $result = [];

        // The result is returned in a query-string format:
        // access_token=xyz&other_thing=xyz
        parse_str($response, $result);

        return $result;
    }

    protected function request($endpoint, $method = 'get', $data = [], $isLogin = false, $base = 'https://api.github.com/')
    {
        $options = [
            'timeout' => 15,
        ];

        if (! $isLogin) {
            $options['headers'] = [
                'Authorization' => "Bearer {$this->token()}",
            ];
        }

        if (! empty($data)) {
            $options['json'] = $data;
        }

        $response = (new Client)->request($method, $base . $endpoint, $options);

        if ($isLogin) {
            return $response->getBody()->getContents();
        } else {
            return json_decode((string) $response->getBody(), true);
        }
    }

    /**
     * Get the access token for the given SourceProvider.
     */
    public function token() {
        return $this->source->meta['token'];
    }
}
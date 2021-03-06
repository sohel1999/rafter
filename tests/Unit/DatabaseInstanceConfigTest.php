<?php

namespace Tests\Unit;

use App\GoogleCloud\DatabaseInstanceConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseInstanceConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_defaults_are_set()
    {
        $instance = factory('App\DatabaseInstance')->make();

        $config = (new DatabaseInstanceConfig($instance))->config();

        $this->assertEquals('MYSQL_5_7', $config['databaseVersion']);
        $this->assertEquals([
            'tier' => 'db-f1-micro',
            'kind' => 'sql#settings',
            'dataDiskSizeGb' => 10,
            'backupConfiguration' => [
                'enabled' => true,
                'kind' => 'sql#backupConfiguration',
                'binaryLogEnabled' => true,
            ],
        ], $config['settings']);
        $this->assertEquals($instance->name, $config['name']);
        $this->assertEquals('us-central1', $config['region']);
        $this->assertEquals('notapassword', $config['rootPassword']);
    }
}

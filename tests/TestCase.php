<?php

namespace Inerba\DbConfig\Tests;

use Inerba\DbConfig\DbConfigServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        // $migration = include __DIR__ . '/../database/migrations/create_db_config_table.php.stub';
        // $migration->up();
    }

    protected function getPackageProviders($app)
    {
        return [
            DbConfigServiceProvider::class,
        ];
    }

    /**
     * Definisci l'ambiente di test.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    public function getEnvironmentSetUp($app)
    {
        // Usiamo un database SQLite in memoria: è velocissimo e non lascia file spazzatura.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}

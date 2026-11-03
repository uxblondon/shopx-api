<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.$connection.database");
        if (! (($connection === 'sqlite' && $database === ':memory:') ||
            ($connection === 'mysql' && $database === 'shopx_test'))) {
            throw new \RuntimeException('Tests require SQLite :memory: or the disposable shopx_test MySQL database.');
        }

        return $app;
    }
}

<?php

namespace Migrator\Seeder;

use Illuminate\Database\Seeder as LaravelSeeder;

/**
 * Class Seeder.
 */
class DatabaseSeeder extends LaravelSeeder
{
    public function run()
    {
        $seeders = app('migrator.seeder.manager')->getSeeders();

        $seeders->each(function ($seeder) {
            $this->call($seeder);
        });
    }
}

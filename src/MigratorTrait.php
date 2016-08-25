<?php

namespace Migrator;

trait MigratorTrait
{
    public function migrations($migrations, $alias = null)
    {
        if (is_array($migrations)) {
            foreach($migrations as $migration) {
                $this->app['migrator.instance']->registerMigration($migration);
            }
        } else {
            $this->app['migrator.instance']->registerMigration($migrations, $alias);
        }

    }

    public function seeders($seeders)
    {
        if (is_array($seeders)) {
            foreach($seeders as $seeder) {
                $this->app['migrator.seeder.manager']->addSeeder($seeder);
            }
        } else {
            $this->app['migrator.seeder.manager']->addSeeder($seeders);
        }
    }
}
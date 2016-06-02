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
}
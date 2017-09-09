<?php

namespace Migrator\Seeder;

/**
 * Class Manager.
 */
class Manager
{
    protected $seeders = [];

    public function addSeeder($seederClass)
    {
        $this->seeders[] = $seederClass;
    }

    public function getSeeders()
    {
        return collect($this->seeders);
    }
}

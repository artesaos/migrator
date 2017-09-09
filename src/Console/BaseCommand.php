<?php

namespace Migrator\Console;

use Illuminate\Console\Command;

class BaseCommand extends Command
{
    // Empty Base Command.
    public function handle()
    {
        return $this->fire();
    }
}

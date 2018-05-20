<?php

namespace Migrator\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Symfony\Component\Console\Input\InputOption;

class FreshCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrator:fresh';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run all migrations';

    /**
     * Execute the console command.
     */
    public function fire()
    {
        $this->handle();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->confirmToProceed()) {
            return;
        }
        $this->dropAllTables(
            $database = $this->input->getOption('database')
        );
        $this->info('Dropped all tables successfully.');
        $options = [
            '--database' => $database,
            '--force' => true,
        ];
        if ($this->needsSeeding()) {
            $options['--seed'] = true;
        }

        $this->call('migrator', $options);
    }

    /**
     * Drop all of the database tables.
     *
     * @param string $database
     */
    protected function dropAllTables($database)
    {
        $this->laravel['db']->connection($database)
                    ->getSchemaBuilder()
                    ->dropAllTables();
    }

    /**
     * Determine if the developer has requested database seeding.
     *
     * @return bool
     */
    protected function needsSeeding()
    {
        return $this->option('seed') || $this->option('seeder');
    }

    /**
     * Run the database seeder command.
     *
     * @param string $database
     */
    protected function runSeeder($database)
    {
        $this->call('db:seed', [
            '--database' => $database,
            '--class' => $this->option('seeder') ?: 'DatabaseSeeder',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'],
            ['seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run.'],
            ['seeder', null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder.'],
        ];
    }
}

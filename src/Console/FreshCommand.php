<?php

namespace Migrator\Console;

use Illuminate\Console\ConfirmableTrait;
use Symfony\Component\Console\Input\InputOption;
use DB;

class FreshCommand extends BaseCommand
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
    
    public function fire()
    {
        return $this->handle();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return;
        }
        $this->dropAllTables(
            $database = $this->input->getOption('database')
        );
        $this->info('Dropped all tables successfully.');
        $this->call('migrator', [
            '--database' => $database,
            '--force' => true,
        ]);
        if ($this->needsSeeding()) {
            $this->runSeeder($database);
        }
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
       * @param  string  $database
       * @return void
       */
      protected function runSeeder($database)
      {
          $class = $this->option('seeder') ?: 'Migrator\Seeder\DatabaseSeeder';

          $force = $this->input->getOption('force');

          $this->call('db:seed', [
              '--database' => $database, '--class' => $class, '--force' => $force,
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
            ['path', null, InputOption::VALUE_OPTIONAL, 'The path of migrations files to be executed.'],
            ['seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run.'],
            ['seeder', null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder.'],
        ];
    }
    
    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function dropAllTables($database)
    {
        $tables = $this->getAllTables();
        if (empty($tables)) {
            return;
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $table) {
            DB::select("DROP TABLE {$table}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Get all of the table names for the database.
     *
     * @return array
     */
    protected function getAllTables()
    {
        return array_map('reset', DB::select('SHOW TABLES'));
    }
}
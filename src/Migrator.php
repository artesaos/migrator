<?php

namespace Migrator;

use Illuminate\Support\Arr;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\ConnectionResolverInterface as Resolver;

class Migrator
{
    /**
     * @var array List of migration classes that should run.
     */
    protected $migrations;

    /**
     * The migration repository implementation.
     *
     * @var \Migrator\MigrationRepositoryInterface
     */
    protected $repository;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $connection;

    /**
     * The notes for the current operation.
     *
     * @var array
     */
    protected $notes = [];

    /**
     * Create a new migrator instance.
     *
     * @param  \Migrator\MigrationRepositoryInterface  $repository
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @param  \Illuminate\Filesystem\Filesystem  $files
     */
    public function __construct(MigrationRepositoryInterface $repository,
                                Resolver $resolver,
                                Filesystem $files)
    {
        $this->files = $files;
        $this->resolver = $resolver;
        $this->repository = $repository;
    }

    /**
     * Register a single Migration to Run
     * @param $class
     * @param null $alias
     */
    public function registerMigration($class, $alias = null)
    {
        if ($alias) {
            $this->migrations[$alias] = $class;
        } else {
            $this->migrations[] = $class;
        }
    }

    /**
     * Forget a registered Migration
     *
     * @param $class
     * @param null $alias
     */
    public function forgetMigration($class, $alias = null)
    {
        if ($alias && array_key_exists($alias, $this->migrations)) {
            unset($this->migrations[$alias]);
        } elseif (in_array($class, $this->migrations)) {
            $key = array_search($class, $this->migrations);
            unset($this->migrations[$key]);
        }
    }

    /**
     * Run the outstanding migrations.
     *
     * @param  array  $options
     */
    public function run(array $options = [])
    {
        $this->notes = [];

        $allMigrations = $this->getMigrations();

        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $ran = $this->repository->getRan();
        
        $migrations = array_diff($allMigrations, $ran);

        $this->runMigrationList($migrations, $options);
    }

    /**
     * Run an array of migrations.
     *
     * @param  array  $migrations
     * @param  array  $options
     * @return void
     */
    public function runMigrationList($migrations, array $options = [])
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($migrations) == 0) {
            $this->note('<info>Nothing to migrate.</info>');

            return;
        }

        $batch = $this->repository->getNextBatchNumber();

        $pretend = Arr::get($options, 'pretend', false);

        $step = Arr::get($options, 'step', false);

        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.
        foreach ($migrations as $file) {
            $this->runUp($file, $batch, $pretend);

            // If we are stepping through the migrations, then we will increment the
            // batch value for each individual migration that is run. That way we
            // can run "artisan migrate:rollback" and undo them one at a time.
            if ($step) {
                $batch++;
            }
        }
    }

    /**
     * Run "up" a migration instance.
     *
     * @param  string  $class
     * @param  int     $batch
     * @param  bool    $pretend
     * @return void
     */
    protected function runUp($class, $batch, $pretend)
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $migration = $this->resolve($class);

        if ($pretend) {
            return $this->pretendToRun($migration, 'up');
        }

        $migration->up();

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($class, $batch);

        $this->note("<info>Migrated:</info> $class");
    }

    /**
     * Rollback the last migration operation.
     *
     * @param  bool  $pretend
     * @return int
     */
    public function rollback($pretend = false)
    {
        $this->notes = [];

        // We want to pull in the last batch of migrations that ran on the previous
        // migration operation. We'll then reverse those migrations and run each
        // of them "down" to reverse the last migration "operation" which ran.
        $migrations = $this->repository->getLast();

        $count = count($migrations);

        if ($count === 0) {
            $this->note('<info>Nothing to rollback.</info>');
        } else {

            // create an empty collection that will hold
            // the inverse order migrations
            $rollbackMigrations = collect([]);

            // loop through available migrations
            foreach ($this->migrations as $registeredMigration) {
                // loop to search, on the ran migrations
                foreach ($migrations as $ranMigration) {
                    // if the available migration is already ran
                    // push into the list
                    if ($ranMigration->migration == $registeredMigration) {
                        $rollbackMigrations->push($ranMigration);
                    }
                }
            }

            // now that we discovered the correct order to
            // inverse the migrations, reverse the order to rollback
            $rollbackMigrations = $rollbackMigrations->reverse();

            // We need to reverse these migrations so that they are "downed" in reverse
            // to what they run on "up". It lets us backtrack through the migrations
            // and properly reverse the entire database schema operation that ran.
            foreach ($rollbackMigrations as $migration) {
                $this->runDown((object) $migration, $pretend);
            }
        }

        return $count;
    }

    /**
     * Rolls all of the currently applied migrations back.
     *
     * @param  bool  $pretend
     * @return int
     */
    public function reset($pretend = false)
    {
        $this->notes = [];

        $migrations = $this->repository->getRan();

        $count = count($migrations);

        if ($count === 0) {
            $this->note('<info>Nothing to rollback.</info>');
        } else {

            // create an empty collection that will hold
            // the inverse order migrations
            $resetMigrations = collect([]);

            // loop through available migrations
            foreach ($this->migrations as $registeredMigration) {
                // loop to search, on the ran migrations
                foreach ($migrations as $ranMigration) {
                    // if the available migration is already ran
                    // push into the list
                    if ($ranMigration == $registeredMigration) {
                        $resetMigrations->push($ranMigration);
                    }
                }
            }

            // now that we discovered the correct order to
            // inverse the migrations, reverse the order to reset (rollback)
            $resetMigrations = $resetMigrations->reverse();

            foreach ($resetMigrations as $migration) {
                $this->runDown((object) ['migration' => $migration], $pretend);
            }
        }

        return $count;
    }

    /**
     * Run "down" a migration instance.
     *
     * @param  object  $migration
     * @param  bool    $pretend
     * @return void
     */
    protected function runDown($migration, $pretend)
    {
        $class = $migration->migration;

        // First we will get the file name of the migration so we can resolve out an
        // instance of the migration. Once we get an instance we can either run a
        // pretend execution of the migration or we can run the real migration.
        $instance = $this->resolve($class);

        if ($pretend) {
            return $this->pretendToRun($instance, 'down');
        }

        $instance->down();

        // Once we have successfully run the migration "down" we will remove it from
        // the migration repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($migration);

        $this->note("<info>Rolled back:</info> $class");
    }

    /**
     * Get all of the migration classes.
     *
     * @return array
     */
    public function getMigrations()
    {
        return (array) $this->migrations;
    }

    /**
     * Pretend to run the migrations.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return void
     */
    protected function pretendToRun($migration, $method)
    {
        foreach ($this->getQueries($migration, $method) as $query) {
            $name = get_class($migration);

            $this->note("<info>{$name}:</info> {$query['query']}");
        }
    }

    /**
     * Get all of the queries that would be run for a migration.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return array
     */
    protected function getQueries($migration, $method)
    {
        $connection = $migration->getConnection();

        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this migration.
        $db = $this->resolveConnection($connection);

        return $db->pretend(function () use ($migration, $method) {
            $migration->$method();
        });
    }

    /**
     * Create a instance of a registered migration
     *
     * @param  string  $class
     * @return object
     */
    public function resolve($class)
    {
        return new $class;
    }

    /**
     * Raise a note event for the migrator.
     *
     * @param  string  $message
     * @return void
     */
    protected function note($message)
    {
        $this->notes[] = $message;
    }

    /**
     * Get the notes for the last operation.
     *
     * @return array
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Resolve the database connection instance.
     *
     * @param  string  $connection
     * @return \Illuminate\Database\Connection
     */
    public function resolveConnection($connection)
    {
        return $this->resolver->connection($connection);
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setConnection($name)
    {
        if (! is_null($name)) {
            $this->resolver->setDefaultConnection($name);
        }

        $this->repository->setSource($name);

        $this->connection = $name;
    }

    /**
     * Get the migration repository instance.
     *
     * @return \Illuminate\Database\Migrations\MigrationRepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists()
    {
        return $this->repository->repositoryExists();
    }

    /**
     * Get the file system instance.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }
}

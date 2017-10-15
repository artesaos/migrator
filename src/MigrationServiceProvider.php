<?php

namespace Migrator;

use Illuminate\Support\ServiceProvider;
use Migrator\Console\FreshCommand;
use Migrator\Console\InstallCommand;
use Migrator\Console\MigrateCommand;
use Migrator\Console\MigratorMakeCommand;
use Migrator\Console\RefreshCommand;
use Migrator\Console\ResetCommand;
use Migrator\Console\RollbackCommand;
use Migrator\Console\StatusCommand;
use Migrator\Seeder\Manager;

class MigrationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRepository();

        // Once we have registered the migrator instance we will go ahead and register
        // all of the migration related commands that are used by the "Artisan" CLI
        // so that they may be easily accessed for registering with the consoles.
        $this->registerMigrator();

        $this->registerSeeder();

        $this->registerCreator();

        $this->registerCommands();
    }

    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->singleton('migrator.repository', function ($app) {
            $table = $app['config']['database.migrations'];

            return new DatabaseMigrationRepository($app['db'], $table);
        });
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerMigrator()
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton('migrator.instance', function ($app) {
            $repository = $app['migrator.repository'];

            return new Migrator($repository, $app['db'], $app['files']);
        });
    }

    /**
     * Register the migration creator.
     *
     * @return void
     */
    protected function registerCreator()
    {
        $this->app->singleton('migrator.creator', function ($app) {
            return new MigrationCreator($app['files']);
        });
    }

    /**
     * Register all of the migration commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $commands = ['Migrate', 'Fresh', 'Rollback', 'Reset', 'Refresh', 'Install', 'Make', 'Status'];

        // We'll simply spin through the list of commands that are migration related
        // and register each one of them with an application container. They will
        // be resolved in the Artisan start file and registered on the console.
        foreach ($commands as $command) {
            $this->{'register'.$command.'Command'}();
        }

        // Once the commands are registered in the application IoC container we will
        // register them with the Artisan start event so that these are available
        // when the Artisan application actually starts up and is getting used.
        $this->commands(
            'command.migrator', 'command.migrator.make',
            'command.migrator.fresh', 'command.migrator.install',
            'command.migrator.rollback', 'command.migrator.reset',
            'command.migrator.refresh', 'command.migrator.status'
        );
    }


    /**
     * Register the "refresh" migration command.
     *
     * @return void
     */
    protected function registerFreshCommand()
    {
        $this->app->singleton('command.migrator.fresh', function () {
            return new FreshCommand();
        });
    }

    /**
     * Register the "migrate" migration command.
     *
     * @return void
     */
    protected function registerMigrateCommand()
    {
        $this->app->singleton('command.migrator', function ($app) {
            return new MigrateCommand($app['migrator.instance']);
        });
    }

    /**
     * Register the "rollback" migration command.
     *
     * @return void
     */
    protected function registerRollbackCommand()
    {
        $this->app->singleton('command.migrator.rollback', function ($app) {
            return new RollbackCommand($app['migrator.instance']);
        });
    }

    /**
     * Register the "reset" migration command.
     *
     * @return void
     */
    protected function registerResetCommand()
    {
        $this->app->singleton('command.migrator.reset', function ($app) {
            return new ResetCommand($app['migrator.instance']);
        });
    }

    /**
     * Register the "refresh" migration command.
     *
     * @return void
     */
    protected function registerRefreshCommand()
    {
        $this->app->singleton('command.migrator.refresh', function () {
            return new RefreshCommand();
        });
    }

    /**
     * Register the "make" migration command.
     *
     * @return void
     */
    protected function registerMakeCommand()
    {
        $this->app->singleton('command.migrator.make', function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['migrator.creator'];

            $composer = $app['composer'];

            return new MigratorMakeCommand($creator, $composer);
        });
    }

    /**
     * Register the "status" migration command.
     *
     * @return void
     */
    protected function registerStatusCommand()
    {
        $this->app->singleton('command.migrator.status', function ($app) {
            return new StatusCommand($app['migrator.instance']);
        });
    }

    /**
     * Register the "install" migration command.
     *
     * @return void
     */
    protected function registerInstallCommand()
    {
        $this->app->singleton('command.migrator.install', function ($app) {
            return new InstallCommand($app['migrator.repository']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'migrator.instance', 'migrator.repository', 'command.migrator',
            'command.migrator.rollback', 'command.migrator.reset',
            'command.migrator.refresh', 'command.migrator.install',
            'command.migrator.status', 'migrator.creator',
            'command.migrator.make',
        ];
    }

    protected function registerSeeder()
    {
        $this->app->singleton('migrator.seeder.manager', function () {
            return new Manager();
        });
    }
}

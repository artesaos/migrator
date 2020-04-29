
# marcelohoffmeister/migrator

[![Latest Stable Version](https://poser.pugx.org/artesaos/migrator/v/stable)](https://packagist.org/packages/artesaos/migrator) [![Total Downloads](https://poser.pugx.org/artesaos/migrator/downloads)](https://packagist.org/packages/artesaos/migrator) [![Monthly Downloads](https://poser.pugx.org/artesaos/migrator/d/monthly)](https://packagist.org/packages/artesaos/migrator) [![License](https://poser.pugx.org/artesaos/migrator/license)](https://packagist.org/packages/artesaos/migrator)

This package is a customized version of Laravel's default database migrator, it was designed to register migrations on services providers and support namespacing as well.

There is no timestamp previews since the run order is based on how you register the migrations.

### Warning
This Package Supports Laravel starting on 5.2 up to the latest stable version.

### Installing
In order to install Migrator, run the following command into your Laravel 6.0+ project:

```
composer require marcelohoffmeister/migrator
```

After installing the Package, you can now register it's provider into your config/app.php file:

```php
'providers' => [
    // other providers omitted.
  Migrator\MigrationServiceProvider::class,
]
```

And publish configuration: with

```
php artisan vendor:publish --provider="Migrator\MigrationServiceProvider"
```

### Usage

As the default Laravel migrator, this one has all the original commands, to list the available options, you can see all the available options using `php artisan` command.

```
migrator            Run the database migrations
migrator:fresh      Drop all tables and re-run all migrations
migrator:install    Create the migration repository
migrator:make       Create a new migration file
migrator:refresh    Reset and re-run all migrations
migrator:reset      Rollback all database migrations
migrator:rollback   Rollback the last database migration
migrator:status     Show the status of each migration
```

#### Creating Migrations
In order to generate an empty migration, please provide the migrator with the full qualified class name, as the example.

`php artisan migrator:make 'MyApp\MyModule\Database\Migrations\CreateOrdersTable' --create=orders`

This will create a migration class into the right directory, the resulting file is slightly different from the default Laravel generated:

```php
<?php

namespace MyApp\MyModule\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
 * @var \Illuminate\Database\Schema\Builder
 */  protected $schema;

    /**
 * Migration constructor. */  public function __construct()
     {
         $this->schema = app('db')->connection()->getSchemaBuilder();
     }

    /**
 * Run the migrations. * * @return void
 */  public function up()
    {
        $this->schema->create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
        });
    }

    /**
 * Reverse the migrations. * * @return void
 */  public function down()
    {
        $this->schema->drop('orders');
    }
}
```

To declare your table fields, just follow the usual schema build practices, this package don't make anything different there.

As the normal migrator, you can pass the option `--table` instead of `--create` in order to generate a update migration instead of a create one. Also, you can create a empty migration not passing any of those options.

**In this fork, you can pass the option --path for the fresh command. This execute the command in the specific path.**

#### Registering migrations.
Inside any service provider of your choice (usually on the same namespace that you're storing the migrations), you easily register the migrations using the *`Migrator\MigratorTrait`*:

```php
<?php

namespace MyApp\MyModule\Providers;
  
use Illuminate\Support\ServiceProvider;
use Migrator\MigratorTrait;
use MyApp\MyModule\Database\Migrations\CreateOrdersTable;
use MyApp\MyModule\Database\Migrations\CreateProductsTable;

class MyModuleServiceProvider extends ServiceProvider
{
    use MigratorTrait;

    public function register()
    {
        $this->migrations([
            CreateOrdersTable::class,
            CreateProductsTable::class,
        ]);
    }
}
```
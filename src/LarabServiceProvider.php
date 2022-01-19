<?php

namespace VirX\Larab;

use Illuminate\Support\ServiceProvider;
use VirX\Larab\Console\InstallLarab;
use VirX\Larab\Console\SchemaAuditLarab;
use VirX\Larab\Console\SchemaBuildLarab;
use VirX\Larab\Console\ApiNewLarab;

class LarabServiceProvider extends ServiceProvider
{
  public function register()
  {
    //
  }

  public function boot()
  {
    // Register the command if we are using the application via the CLI
    if ($this->app->runningInConsole()) {
      $this->commands([
        InstallLarab::class,
        SchemaAuditLarab::class,
        SchemaBuildLarab::class,
        ApiNewLarab::class
      ]);

      $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
      $this->mergeConfigFrom(__DIR__ . '/../config/api.php', 'api');
      $this->mergeConfigFrom(__DIR__ . '/../config/larab.php', 'larab');

      $this->publishes([
        __DIR__ . '/../config/api.php' => config_path('api.php'),
      ], 'config');
      $this->publishes([
        __DIR__ . '/../config/larab.php' => config_path('larab.php'),
      ], 'config');

    }
  }
}

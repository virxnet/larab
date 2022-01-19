<?php

namespace VirX\Larab\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class InstallLarab extends Command
{
    protected $hidden = true;
    
    protected $signature = 'larab:install';

    protected $description = 'Install VirX Larab';

    public function handle()
    {
        echo "\n\n";
        $this->warn("WARNING! Please check that the repo is clean and database is clear ");
        $this->warn("WARNING! This installer is intended to be run on a fresh Laravel installation only.");
        if (!$this->confirm('Are you sure you want to wipe the database and install Larab?', false)) {
            $this->error('User Aborted');
        }
        
        $this->info('Installing VirX Larab...');

        $this->call('migrate:reset');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        $this->call('key:generate');
        $this->call('larab:api:new');

        if ($this->confirm('Install Backpack + PermissionManager and migrate? (you need it for Larab to work, only skip if already installed)', true)) {
            $this->info('Installing Backpack...');
            $this->call('backpack:install');
            $this->info('Setting up PermissioManager and migrations...');
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'migrations'
            ]);
            $this->call('migrate');
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'config'
            ]);
            $this->info('Updating User Model...');
            /*
            $user_model_file = File::get('./app/Models/User.php');
            $new_line1 = "use Spatie\Permission\Traits\HasRoles;\n\nclass User extends";
            $new_line2 = 'use HasRoles, HasApiTokens,';
            $new_line3 = "protected \$fillable = [\n\t\t'uid',\n\t\t'first_name',\n\t\t'last_name',\n\t\t'is_active',\n\t\t'api_enabled',";
            $user_model_file = str_replace('class User extends', $new_line1, $user_model_file);
            $user_model_file = str_replace('use HasApiTokens,', $new_line2, $user_model_file);
            $user_model_file = str_replace('protected \$fillable = [', $new_line3, $user_model_file);
            File::put('./app/Models/User.php', $user_model_file);
            if (!$this->confirm('Did you manually check ./app/Models/User.php for sanity?', false)) {
                $this->error('You need to make sure the User Model is fine');
                exit();
            }
            */
            $this->info('Setting up PermissioManager and migrations...');
            $this->call('vendor:publish', [
                '--provider' => 'Backpack\PermissionManager\PermissionManagerServiceProvider',
                '--tag' => 'config'
            ]);
            $this->call('vendor:publish', [
                '--provider' => 'Backpack\PermissionManager\PermissionManagerServiceProvider',
                '--tag' => 'migrations'
            ]);
            $this->call('migrate');
        }

        

        $this->line(' * Roles...');
        File::copy(__DIR__ . '/../stubs/larab/RoleModel.php.stub', './app/Models/Role.php');
        File::copy(__DIR__ . '/../../database/seeders/RoleSeeder.php', './database/seeders/RoleSeeder.php');
        (new \Database\Seeders\RoleSeeder())->run();
        $this->line(' * Roles Complete!');

        $this->line(' * Users...');
        File::copy(__DIR__ . '/../stubs/larab/UserModel.php.stub', './app/Models/User.php');
        File::copy(__DIR__ . '/../../database/seeders/UserSeeder.php', './database/seeders/UserSeeder.php');
        (new \Database\Seeders\UserSeeder())->run();
        $this->line(' * Users Complete!');

        $this->line(' * App Support Libraries...');
        File::makeDirectory('./app/Support', true);
        File::copy(__DIR__ . '/../Support/SQLQuery.php', './app/Support/SQLQuery.php');
        File::copy(__DIR__ . '/../Support/APIInfo.php', './app/Support/APIInfo.php');
        $this->line(' * App Support Libraries Complete!');

        $this->line(' * Admin Middleware...');
        File::delete('./app/Http/Middleware/CheckIfAdmin.php');
        File::copy(__DIR__ . '/../stubs/larab/CheckIfAdminMiddleware.php.stub', './app/Http/Middleware/CheckIfAdmin.php');
        $this->line(' * Admin Middleware Complete!');

        /* For publishing config the right way (TODO)
        $this->info('Publishing configuration...');

        if (! $this->configExists('larab.php')) {
            $this->publishConfiguration();
            $this->info('Published configuration');
        } else {
            if ($this->shouldOverwriteConfig()) {
                $this->info('Overwriting configuration file...');
                $this->publishConfiguration($force = true);
            } else {
                $this->info('Existing configuration was not overwritten');
            }
        }
        */

        $this->warn('Due to bugs you may want to check the installation manually');
        $this->error('A known issue is that backpack is not installed correctly, please run "composer require --dev backpack/generators"');
        echo "\n\n";
        $this->info('Installed VirX Larab');
    }

    private function configExists($fileName)
    {
        return File::exists(config_path($fileName));
    }

    private function shouldOverwriteConfig()
    {
        return $this->confirm(
            'Config file already exists. Do you want to overwrite it?',
            false
        );
    }

    private function publishConfiguration($forcePublish = false)
    {
        $params = [
            '--provider' => "VirX\Larab\LarabServiceProvider",
            '--tag' => "config"
        ];

        if ($forcePublish === true) {
            $params['--force'] = true;
        }

       $this->call('vendor:publish', $params);
    }
}

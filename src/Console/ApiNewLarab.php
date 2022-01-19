<?php

namespace VirX\Larab\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ApiNewLarab extends Command
{
    protected $hidden = true;
    
    protected $signature = 'larab:api:new {version=1}';

    protected $description = 'API New Larab';

    public function handle()
    {
        
        $version = $this->argument('version');
        $api_route_file = "./routes/api_v{$version}.php";

        if (!File::exists('./config/api.php')) {
            $this->warn('API config has not been published');
            if ($this->confirm('Publish the API config now?', true)) {
                $this->call('vendor:publish', [
                    '--provider' => 'VirX\\Larab\\LarabServiceProvider',
                    '--tag' => 'config'
                ]);
            }
        }

        if (!File::exists('./config/api.php')) {
            $this->error('You need to publish the API config first');
            exit();
        }

        if (!self::isStrInFile('./app/Providers/RouteServiceProvider.php', '//AutoApiRoutes')) {
            $this->warn('You need to update the route service provider');
            $new_lines = "\$this->routes(function () {\n\n";
            $new_lines .= "\t\t\t//AutoApiRoutes\n";
            $new_lines .= "\t\t\tforeach (config('api.apis') as \$api) {\n";
            $new_lines .= "\t\t\t\tRoute::prefix(\$api['path'])\n";
            $new_lines .= "\t\t\t\t\t->middleware('api')\n";
            $new_lines .= "\t\t\t\t\t->namespace(\$this->namespace)\n";
            $new_lines .= "\t\t\t\t\t->group(base_path(\$api['route_file']));\n";
            $new_lines .= "\t\t\t}\n\n";
            if ($this->confirm('Update route service provider now?', true)) {
                $route_serv_file = File::get('./app/Providers/RouteServiceProvider.php');
                $route_serv_file = str_replace('$this->routes(function () {', $new_lines, $route_serv_file);
                File::put('./app/Providers/RouteServiceProvider.php', $route_serv_file);
            } 
            
            if (!$this->confirm('Did you manually check sanity of ./app/Providers/RouteServiceProvider.php?', false)) {
                $this->info($new_lines);
                $this->error('Manually fix ./app/Providers/RouteServiceProvider.php');
                exit();
            }
        }

        $this->info('Prepare Laravel Passport...');
        $this->line('See install docs for manual steps: https://laravel.com/docs/8.x/passport ');   
        $this->warn(' 1) composer require laravel/passport (can skip if Larab is installed)');
        
        if ($this->confirm('Run Laravel Passport Install Automations? (Do not repeat if done before)', false)) {

            if ($this->confirm('Run all migrations automatically?', false)) {
                $this->call('migrate');
            }
            $this->warn(' 2) php artisan migrate');

            if ($this->confirm('Run passport:install automatically?', false)) {
                $this->call('passport:install');
            }
            $this->warn(' 3) php artisan passport:install');
            
            // Update App\Models\User
            $this->line('Updating ./app/Models/User.php ...');
            $user_model_file = File::get('./app/Models/User.php');
            $user_model_line = 'use Laravel\Passport\HasApiTokens;';
            $user_model_file = str_replace('use Laravel\Sanctum\HasApiTokens;', $user_model_line, $user_model_file);
            File::put('./app/Models/User.php', $user_model_file);

            $this->warn(' 4) Check User.php Model to use Laravel\Passport\HasApiTokens as this was automated');
            /**
             * use Laravel\Passport\HasApiTokens;
             *
             *   class User extends Authenticatable
             *   {
             *       use HasApiTokens, HasFactory, Notifiable;
             *   }
             */

            // Update App\Providers\AuthServiceProvider
            $this->line('Updating ./app/Providers/AuthServiceProvider.php ...');
            $auth_serv_file = File::get('./app/Providers/AuthServiceProvider.php');
            $auth_serv_line = "\n\t\tif (! \$this->app->routesAreCached()) { \Laravel\Passport\Passport::routes(); }\n";
            $auth_serv_file = str_replace('$this->registerPolicies();', "\$this->registerPolicies();{$auth_serv_line}", $auth_serv_file);
            File::put('./app/Providers/AuthServiceProvider.php', $auth_serv_file);

            $this->warn(' 5) Check App\Providers\AuthServiceProvider is correct as this was automated');
            /**
             * use Laravel\Passport\Passport;
             * public function boot()
             *   {
             *      $this->registerPolicies();
             *
             *       if (! $this->app->routesAreCached()) {
             *           Passport::routes();
             *       }
             *   }
             */

            // Update config/auth.php
            $this->line('Updating ./config/auth.php');
            $config_auth_file = File::get('./config/auth.php');
            $passport_auth_cnf = "'api' => ['driver' => 'passport','provider' => 'users']";
            $config_auth_file = str_replace("'guards' => [", "'guards' => [\n\t\t{$passport_auth_cnf},\n", $config_auth_file);
            File::put('./config/auth.php', $config_auth_file);

            $this->warn(" 6) Check config/auth.php matches {$api_route_file} accordingly as this was automated");
            /**
             * 'guards' => [ ...
             *      'api' => [
             *          'driver' => 'passport',
             *          'provider' => 'users',
             *       ],
             */
        }

        

        if (!$this->confirm('Did you manually check the Laravel Passport installation?', false)) {
            $this->error('Install Laravel Passport first');
            exit();
        }
        
        if (!$this->confirm('Did you run the migrations?', false)) {
            if ($this->confirm('Run migrations now?', true)) {
                $this->call('migrate');
            }
        }
        
        $this->info('Creating API router...');
        $tpl = File::get(realpath(__DIR__ . '/../stubs/larab/api_routes.php.stub')); // TODO: make this configurable
        // Update tpl placeholder
        File::put($api_route_file, $tpl); // TODO: this too (and all others)

        $this->info('Updating API Config...');
        $cnf = File::get('./config/api.php');
        $new_cnf_lines = "1 => ['path' => 'api/v{$version}', 'route_file' => '{$api_route_file}'],\n\t\t// NewApis";
        $cnf = str_replace('// NewApis', $new_cnf_lines, $cnf);
        File::put('./config/api.php', $cnf);

        $this->info('Creating API Auth Controller...');
        $tpl = File::get(realpath(__DIR__ . '/../stubs/larab/ApiAuthController.php.stub')); // TODO: make this configurable
        // Update tpl placeholder
        File::put('./app/Http/Controllers/ApiAuthController.php', $tpl);

        $this->info('Creating ActionLog Model...');
        $tpl = File::get(realpath(__DIR__ . '/../stubs/larab/ActionLogModel.php.stub')); 
        // Update tpl placeholder
        File::put('./app/Models/ActionLog.php', $tpl);

        $this->info('Creating ClientSession Model...');
        $tpl = File::get(realpath(__DIR__ . '/../stubs/larab/ClientSessionModel.php.stub')); 
        // Update tpl placeholder
        File::put('./app/Models/ClientSession.php', $tpl);

        


    }


    private static function isStrInFile($file, $str) 
    {
        $handle = fopen($file, 'r');
        while (($buffer = fgets($handle)) !== false) {
            if (strpos($buffer, $str) !== false) {
                return true;
            }      
        }
        fclose($handle);

        return false;
    }

}

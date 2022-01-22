<?php

namespace VirX\Larab\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class SchemaBuildLarab extends Command
{
    protected $hidden = true;
    
    protected $signature = 'larab:schema:build {resource} {--S|schema=} {--A|api=1}';

    protected $description = 'Schema Build Larab';

    public function handle(Filesystem $filesystem)
    {
        
        if (!isset(config('api.apis')[$this->option('api')]['path'])) {
            if ($this->confirm("Specified API does not exist in config. Yes to use default instead or No to abort", true)) {
                if (isset(config('api.apis')[config('api.default')]['path'])) {
                    $api_path = config('api.apis')[config('api.default')]['path'];
                    $api_route_file = config('api.apis')[config('api.default')]['route_file'];
                } else {
                    $this->error('Default missing in config. Aborted!');
                    if ($this->confirm('Would you like install Larab and setup a new API?', true)) {
                        $this->call('larab:install');
                        $this->error('Original request was still aborted but you can try again');
                        $this->info('Now that a new API has been setup, try your previous command again');
                    }
                    exit();
                }
            } else {
                $this->error('Aborted!');
                exit();
            }
        } else {
            $api_path = config('api.apis')[$this->option('api')]['path'];
            $api_route_file = config('api.apis')[$this->option('api')]['route_file'];
        }

        // Prep
        $swapped_stubs = false;
        $stubs_backup = './stubs_backup' . time();
        $clean_up = true;
        $flash_stubs = true;
        if (is_dir('./stubs')) {
            $flash_stubs = false;
            $this->error('Stubs (which are potentially conflicting) already exist on this Laravel project, they may not be compatible with Larab');
            if (!$this->confirm('[ADVANCED USERS ONLY] Are you sure you want to use the existing stubs?', false)) {
                if ($this->confirm('[RECOMMENDED] Would you like to temporarily swap with Larab stubs to?', true)) {
                    if (!rename('./stubs', $stubs_backup)) {
                        $this->error('Failed!');
                        exit();
                    }
                    $swapped_stubs = true;
                    $flash_stubs = true;
                }
            }
            $clean_up = false;
        } 
        
        if ($flash_stubs === true) {
            $this->info('Loading Larab Stubs...');
            @$filesystem->makeDirectory('./stubs');
            $stub_files = File::files(__DIR__ . '/../stubs/laravel');

            foreach ($stub_files as $file) {
                $this->info('Copying ' . $file->getBasename());
                File::copy($file->getRealPath(), './stubs/'.$file->getBasename());
            }
        }
        

        $api_name = str_replace('/', '_', $api_path);
        $api_ns_path = str_replace('/', '\\', $api_path);
        
        $res = Str::singular($this->argument('resource'));
        $ress = Str::pluralStudly($res);
        $table = strtolower(Str::snake($ress));
        $schema = $this->option('schema');
        $schema_arr = explode(',', $schema);
        $schema_marr = [];
        $rules = [];
        foreach ($schema_arr as $key => $col) {
            if (strlen($col) > 0) {
                $col = explode(':', $col);
                $schema_marr[] = [
                    'name' => trim(@$col[0]),
                    'type' => trim(@$col[1]),
                    'attrs' => trim(@$col[2])
                ];
                $rules[trim(@$col[0])] = '';
                if (!isset($col[2])) { // not nullable
                    $rules[trim(@$col[0])] .= "required";
                } elseif (trim($col[2]) == 'unique') {
                    $rules[trim(@$col[0])] .= "unique:{$table}";
                }
            } else {
                unset($schema_arr[$key]);
            }
        }

        var_dump($schema_marr);
        if ($this->confirm("Begin [{$res}] Init {$api_path}?", false)) {
            $this->line('Building Migration and Model...');
            $this->call("make:migration:schema", [   // TODO: drop this package and generate natively with api name support for migrations?
                'name' => strtolower("create_{$table}_table"),
                '--schema' => implode(',', $schema_arr),
                '--model' => true
            ]);
        
            $model_file = app_path() . "/Models/{$res}.php"; // TODO: define in config 
            if ($filesystem->exists($model_file)) {
                $this->line('Updating Model...');
                $contents = $filesystem->get($model_file);
                $fillable = '';
                foreach ($schema_marr as $col) {
                    $fillable .= "\n\t\t'" . $col['name'] . "',";
                }
                $fillable .= "\n\t";
                $contents = str_replace('// FillableArray', $fillable, $contents);
                $filesystem->put($model_file, $contents, true);
                $this->line('Model updated successfully');
            } else {
                $this->error("Model file {$model_file} not detected for auto update");
            }

            $this->line('Building Policy');
            $this->call('make:policy', [
                'name' => "{$res}Policy",
                '--model' => $res
            ]);

            $this->line('Building Controller...');
            $this->call("make:controller", [
                'name' => "/{$api_path}/{$res}Controller",
                '--resource' => true,
                '--requests' => true,
                '--pest' => true
            ]);

            $controller_file = app_path() . "/Http/Controllers/{$api_path}/{$res}Controller.php"; // TODO: define in CONFIG
            if ($filesystem->exists($controller_file)) {
                $this->line('Updating Controller...');
                $contents = $filesystem->get($controller_file);
                // UseModel
                $code = "use App\\Models\\{$res};";
                $contents = str_replace('// UseModel', $code, $contents);

                // UseRequest
                $code = "use App\\Http\\Requests\\Store{$res}Request;";
                $contents = str_replace('// UseRequest', $code, $contents);

                // UseResource
                $code = "use App\\Http\\Resources\\{$res}Resource;";
                $contents = str_replace('// UseResource', $code, $contents);

                // UsrRecourceCollection
                $code = "use App\\Http\\Resources\\{$ress}Resource;";
                $contents = str_replace('// UsrRecourceCollection', $code, $contents);

                // ControllerIndex
                $code = "\$query = {$res}::query();\n\t\t";
                $code .= "\$result = SQLQuery::standardFilter(\$request, \$query);\n\t\t";
                $code .= "return new {$ress}Resource(\$result);";
                $contents = str_replace('// ControllerIndex', $code, $contents);

                // ControllerCreate
                $code = "\$model = new {$res};\n\t\t";
                $code .= "\$request = new Store{$res}Request;\n\t\t";
                $code .= "\$table = DB::select('explain {$table}');\n\t\t";
                $code .= "return response()->json(['status' => 'success', 'fields' => \$model->getFillable(), 'rules' => \$request->rules(), 'table' => \$table]);\n\t\t";
                $contents = str_replace('// ControllerCreate', $code, $contents);

                // ControllerStore
                $code = "\$result = {$res}::create(\$request->all());\n\t\t";
                $code .= "return new {$res}Resource(\$result);";
                $contents = str_replace('// ControllerStore', $code, $contents);
                $this->line('Building Request Handler...');
                $this->call("make:request", [
                    'name' => "Store{$res}Request"
                ]);
                $contents = str_replace('store(Request $request', "store(Store{$res}Request \$request", $contents);

                // ControllerShow
                $code = "\$result = {$res}::findOrFail(\$id);\n\t\t";
                $code .= "return new {$res}Resource(\$result);";
                $contents = str_replace('// ControllerShow', $code, $contents);

                // ControllerEdit
                // TODO: enhance later if separate update rules apply

                // ControllerUpdate
                $code = "\$result = {$res}::where('id', \$id)->update(\$request->all());\n\t\t";
                $code .= "\$data = {$res}::findOrFail(\$id);\n\t\t";
                $code .= "if (\$result == 1) {\n\t\t\t";
                $code .= "return response()->json(['status' => 'success', 'message' => \"{$res}#{\$id} updated\", 'data' => \$data]);\n\t\t";
                $code .= "}\n\t\t";
                $code .= "return response()->json(['status' => 'failed', 'message' => \"{$res}#{\$id} not updated\"], 404);";
                $contents = str_replace('// ControllerUpdate', $code, $contents);
                $contents = str_replace('update(Request $request', "update(Store{$res}Request \$request", $contents);

                // ControllerDestroy
                $code = "\$result = {$res}::where('id', \$id)->delete();\n\t\t";
                $code .= "if (\$result == 1) {\n\t\t\t";
                $code .= "return response()->json(['status' => 'success', 'message' => \"{$res}#{\$id} deleted\"]);\n\t\t";
                $code .= "}\n\t\t";
                $code .= "return response()->json(['status' => 'failed', 'message' => \"{$res}#{\$id} not deleted\"], 404);";
                $contents = str_replace('// ControllerDestroy', $code, $contents);

                $filesystem->put($controller_file, $contents, true);
                $this->line('Controller updated successfully');
            } else {
                $this->error("Controller {$controller_file} not detected for auto update");
            }

            $request_file = app_path() . "/Http/Requests/Store{$res}Request.php"; // TODO: define in CONFIG
            if ($filesystem->exists($request_file)) {
                $this->line('Updating Request Rules...');
                $contents = $filesystem->get($request_file);
                $rules_str = '';
                foreach ($rules as $field => $rule) {
                    $rules_str .= "'{$field}' => '{$rule}',\n\t\t\t";
                }
                $contents = str_replace('// RequestRules', $rules_str, $contents);
                $filesystem->put($request_file, $contents, true);
                $this->line('Request updated successfully');

            } else {
                $this->error("Request {$request_file} not detected for auto update");
            }

            $this->line('Building Resources...');
            $this->call("make:resource", [
                'name' => "{$res}Resource"
            ]);
            
            $this->call("make:resource", [
                'name' => "{$ress}Resource",
                '--collection' => true
            ]);

            $routes_file = $api_route_file;
            if ($filesystem->exists($routes_file)) {
                $this->line("Creating Routes for {$api_path}...");
                $contents = $filesystem->get($routes_file);

                $routes = "// {$res} -----";

                // GET info
                $routes .= "\nRoute::get('/" 
                        . strtolower(Str::plural($res)) . 
                        "_info', [App\\Http\\Controllers\\{$api_ns_path}\\{$res}Controller::class, 'create']);";

                // GET collection
                $routes .= "\nRoute::middleware('auth:api')->get('/" 
                        . strtolower(Str::plural($res)) . 
                        "', [App\\Http\\Controllers\\{$api_ns_path}\\{$res}Controller::class, 'index']);";

                // GET one
                $routes .= "\nRoute::middleware('auth:api')->get('/" 
                        . strtolower($res) . 
                        "/{id}', [App\\Http\\Controllers\\{$api_ns_path}\\{$res}Controller::class, 'show']);";

                // POST new
                $routes .= "\nRoute::middleware('auth:api')->post('/" 
                        . strtolower($res) . 
                        "', [App\\Http\\Controllers\\{$api_ns_path}\\{$res}Controller::class, 'store']);";

                // PUT
                $routes .= "\nRoute::middleware('auth:api')->put('/" 
                        . strtolower($res) . 
                        "/{id}', [App\\Http\\Controllers\\{$api_ns_path}\\{$res}Controller::class, 'update']);";

                // DELETE
                $routes .= "\nRoute::middleware('auth:api')->delete('/" 
                        . strtolower($res) . 
                        "/{id}', [App\\Http\\Controllers\\{$api_ns_path}\\{$res}Controller::class, 'destroy']);";

                $routes .= "\n\n// NewRoutes";

                $contents = str_replace('// NewRoutes', $routes, $contents);

                $filesystem->put($routes_file, $contents, true);

                $this->line('Routes Added!');
            } else {
                $this->error("Route file {$routes_file} not detected for auto update");
            }

            // Migrate
            if ($this->confirm("Run Migrations?", false)) {
                $this->line('Running Migration...');
                $this->call("migrate");
            }
            
            // Backpack Update
            if ($this->confirm("Build Backpack?", false)) {
                $this->line('Building Backpack...');
                $this->call("backpack:build");
            }

        } else {
            $this->error('Aborted!');
        }

        $this->info('Cleaning up...');
        if ($swapped_stubs === true) {
            File::deleteDirectory('./stubs');
            rename($stubs_backup, './stubs');
        } elseif ($clean_up === true) {
            File::deleteDirectory('./stubs');
        }
    }
}

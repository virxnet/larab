<?php

namespace VirX\Larab\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SchemaAuditLarab extends Command
{
    protected $hidden = true;
    
    protected $signature = 'larab:schema:audit {--tables=} {--command=} {--file=}';

    protected $description = 'Schema Audit Larab';

    public function handle()
    {
        
        $tables_opt = $this->option('tables');
        $command_opt = $this->option('command');
        $file_opt = $this->option('file');

        if (strlen($tables_opt) < 1) {
            $tables = \DB::select('SHOW TABLES');
            $tables = array_map('current',$tables);
        } else {
            $tables = explode(',', $tables_opt);
            foreach ($tables as $i => $table) {
                $tables[$i] = trim($table);
            }
        }

        

        if (strlen($file_opt) > 1) {
            if (File::exists($file_opt)) {
                $this->error('Output file already exists ' . $file_opt);
                exit();
            } else {
                File::put($file_opt, "#!/bin/bash\n");
                if (!File::exists($file_opt)) {
                    $this->error('Error writing to file ' . $file_opt);
                    exit();
                }
            }
        }

        foreach ($tables as $i => $table) {

            $this->info("\n####");
            $this->info("# Table: {$table}...");

            $filter_out = ['id', 'created_at', 'updated_at', 'deleted_at'];

            $cols = Schema::getColumnListing($table);

            if (strlen($command_opt) < 1) {
                $model = Str::studly(Str::singular($table));
                $command = "php artisan larab:schema:build {$model} --schema=";
            } else {
                $command = trim($command_opt) . ' ';
            }

            $schema = "{$command}\"\\\n";
            $appends = '';
            foreach ($cols as $index => $col) {
                if (!in_array($col, $filter_out)) {
                    $meta = Db::select(DB::raw("SHOW COLUMNS FROM {$table}"));
                    $type = DB::getSchemaBuilder()->getColumnType($table, $col);
                    if ($meta[$index]->Null == 'YES' && $meta[$index]->Field == $col) {
                        $appends .= ':nullable';
                    }
                    $schema .= "{$col}:{$type}{$appends},\\\n";
                    $appends = '';
                }
            }
            $schema .= chr(8). "\"\n";

            $this->info("####");

            $this->line($schema);

            if (strlen($file_opt) > 1) {
                File::append($file_opt, $schema);
            }

            echo "\n\n";
        
        
        }

        $this->info('# Audit Completed!');
        
        return Command::SUCCESS;
    }


}

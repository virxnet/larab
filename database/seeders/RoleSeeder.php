<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::create([
            'name' => config('larab.role_admin_super'),
            'guard_name' => 'web'
        ]);
        Role::create([
            'name' => config('larab.role_user'),
            'guard_name' => 'web'
        ]);
    }
}

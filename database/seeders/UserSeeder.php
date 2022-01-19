<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create First Super Admin
        $user = User::create([
            'name' => config('larab.user_admin_name'),
            'first_name' => config('larab.user_admin_first_name'),
            'last_name' => config('larab.user_admin_last_name'),
            'email' => config('larab.user_admin_email'),
            'password' => Hash::make(config('larab.user_admin_password'))
        ]);
        $role = Role::where('name', config('larab.role_admin_super'))->first();
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id, 
            'model_type' => 'App\\Models\\User',
            'model_id' => $user->id // Super Admin
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Super Admin — platform-level, no gym assigned
        $admin = User::updateOrCreate(
            ['email' => 'admin@fitnessstudio.com'],
            [
                'name'     => 'Zain Mirza',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        if (! $admin->hasRole('admin')) $admin->assignRole('admin');
        
        $this->call(DemoDataSeeder::class);
    }
}

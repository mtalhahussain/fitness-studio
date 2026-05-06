<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Super Admin (no gym)
        $admin = User::firstOrCreate(
            ['email' => 'admin@fitnessstudio.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $admin->assignRole('admin');

        // Demo Gym
        $gym = Gym::firstOrCreate(
            ['slug' => 'demo-gym'],
            [
                'name'    => 'Demo Fitness Studio',
                'email'   => 'owner@demogym.com',
                'phone'   => '+1234567890',
                'city'    => 'New York',
                'country' => 'US',
                'status'  => 'active',
            ]
        );

        // Gym Owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@demogym.com'],
            [
                'gym_id'   => $gym->id,
                'name'     => 'Gym Owner',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $owner->assignRole('owner');

        // Demo Trainer
        $trainer = User::firstOrCreate(
            ['email' => 'trainer@demogym.com'],
            [
                'gym_id'   => $gym->id,
                'name'     => 'Demo Trainer',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $trainer->assignRole('trainer');

        // Demo Member
        $member = User::firstOrCreate(
            ['email' => 'member@demogym.com'],
            [
                'gym_id'   => $gym->id,
                'name'     => 'Demo Member',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $member->assignRole('member');
    }
}

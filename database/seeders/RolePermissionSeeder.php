<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Gym management
            'gym.view', 'gym.edit', 'gym.settings',

            // Member management
            'members.view', 'members.create', 'members.edit', 'members.delete',

            // Trainer management
            'trainers.view', 'trainers.create', 'trainers.edit', 'trainers.delete',

            // Schedule & Classes
            'classes.view', 'classes.create', 'classes.edit', 'classes.delete',
            'schedule.view', 'schedule.manage',

            // Finance
            'payments.view', 'payments.manage', 'invoices.view', 'invoices.manage',

            // Reports
            'reports.view',

            // Admin only
            'admin.panel', 'gyms.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Admin — full access
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // Owner — full gym control
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->syncPermissions([
            'gym.view', 'gym.edit', 'gym.settings',
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'trainers.view', 'trainers.create', 'trainers.edit', 'trainers.delete',
            'classes.view', 'classes.create', 'classes.edit', 'classes.delete',
            'schedule.view', 'schedule.manage',
            'payments.view', 'payments.manage', 'invoices.view', 'invoices.manage',
            'reports.view',
        ]);

        // Trainer
        $trainer = Role::firstOrCreate(['name' => 'trainer', 'guard_name' => 'web']);
        $trainer->syncPermissions([
            'members.view', 'classes.view', 'classes.create', 'classes.edit',
            'schedule.view', 'schedule.manage',
        ]);

        // Member
        $member = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        $member->syncPermissions([
            'classes.view', 'schedule.view', 'invoices.view',
        ]);
    }
}

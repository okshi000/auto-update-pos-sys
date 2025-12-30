<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin - Full access to everything
        $admin = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Full system access',
                'is_system' => true,
            ]
        );
        // Admin gets all permissions
        $admin->permissions()->sync(Permission::pluck('id')->toArray());

        // Manager - Most operations except system settings
        $manager = Role::updateOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Store management access',
                'is_system' => true,
            ]
        );
        $managerPermissions = Permission::whereIn('group', [
            'users', 'products', 'inventory', 'pos', 'purchases', 'reports'
        ])->pluck('id')->toArray();
        $manager->permissions()->sync($managerPermissions);

        // Cashier - POS and basic product viewing
        $cashier = Role::updateOrCreate(
            ['name' => 'cashier'],
            [
                'display_name' => 'Cashier',
                'description' => 'POS operations only',
                'is_system' => true,
            ]
        );
        $cashierPermissions = Permission::whereIn('name', [
            'pos.access',
            'pos.create_sale',
            'products.view',
            'inventory.view',
        ])->pluck('id')->toArray();
        $cashier->permissions()->sync($cashierPermissions);

        // Warehouse - Inventory management
        $warehouse = Role::updateOrCreate(
            ['name' => 'warehouse'],
            [
                'display_name' => 'Warehouse Staff',
                'description' => 'Inventory and purchasing operations',
                'is_system' => true,
            ]
        );
        $warehousePermissions = Permission::whereIn('name', [
            'products.view',
            'products.print',
            'inventory.view',
            'inventory.adjust',
            'purchases.view',
            'purchases.create',
            'purchases.receive',
        ])->pluck('id')->toArray();
        $warehouse->permissions()->sync($warehousePermissions);

        // Accountant - Financial reports and viewing
        $accountant = Role::updateOrCreate(
            ['name' => 'accountant'],
            [
                'display_name' => 'Accountant',
                'description' => 'Financial reports access',
                'is_system' => true,
            ]
        );
        $accountantPermissions = Permission::whereIn('name', [
            'products.view',
            'inventory.view',
            'purchases.view',
            'reports.view',
            'reports.export',
        ])->pluck('id')->toArray();
        $accountant->permissions()->sync($accountantPermissions);

        // Viewer - Read-only access
        $viewer = Role::updateOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'Viewer',
                'description' => 'Read-only access to system',
                'is_system' => true,
            ]
        );
        $viewerPermissions = Permission::whereIn('name', [
            'products.view',
            'inventory.view',
            'reports.view',
        ])->pluck('id')->toArray();
        $viewer->permissions()->sync($viewerPermissions);
    }
}

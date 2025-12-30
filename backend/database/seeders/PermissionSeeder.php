<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users', 'description' => 'Can view user list and details'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users', 'description' => 'Can create new users'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'group' => 'users', 'description' => 'Can update existing users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'users', 'description' => 'Can delete users'],
            ['name' => 'users.assign_roles', 'display_name' => 'Assign Roles', 'group' => 'users', 'description' => 'Can assign roles to users'],

            // Role Management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles', 'description' => 'Can view roles and permissions'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'roles', 'description' => 'Can create new roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'group' => 'roles', 'description' => 'Can update existing roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'roles', 'description' => 'Can delete roles'],

            // Audit Logs
            ['name' => 'audit.view', 'display_name' => 'View Audit Logs', 'group' => 'audit', 'description' => 'Can view audit logs'],

            // Settings
            ['name' => 'settings.view', 'display_name' => 'View Settings', 'group' => 'settings', 'description' => 'Can view system settings'],
            ['name' => 'settings.update', 'display_name' => 'Update Settings', 'group' => 'settings', 'description' => 'Can update system settings'],

            // Products (Phase 2 - placeholder)
            ['name' => 'products.view', 'display_name' => 'View Products', 'group' => 'products', 'description' => 'Can view products'],
            ['name' => 'products.create', 'display_name' => 'Create Products', 'group' => 'products', 'description' => 'Can create new products'],
            ['name' => 'products.update', 'display_name' => 'Update Products', 'group' => 'products', 'description' => 'Can update existing products'],
            ['name' => 'products.delete', 'display_name' => 'Delete Products', 'group' => 'products', 'description' => 'Can delete products'],
            ['name' => 'products.print', 'display_name' => 'Print Barcodes', 'group' => 'products', 'description' => 'Can print product barcode stickers'],

            // Inventory (Phase 2 - placeholder)
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'group' => 'inventory', 'description' => 'Can view stock levels'],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Stock', 'group' => 'inventory', 'description' => 'Can adjust stock levels'],

            // Sales (Phase 3)
            ['name' => 'sales.view', 'display_name' => 'View Sales', 'group' => 'sales', 'description' => 'Can view sales transactions'],
            ['name' => 'sales.create', 'display_name' => 'Create Sales', 'group' => 'sales', 'description' => 'Can create sales transactions'],
            ['name' => 'sales.refund', 'display_name' => 'Refund Sales', 'group' => 'sales', 'description' => 'Can refund sales transactions'],
            ['name' => 'sales.export', 'display_name' => 'Export Sales', 'group' => 'sales', 'description' => 'Can export sales data'],

            // POS (Phase 3)
            ['name' => 'pos.access', 'display_name' => 'Access POS', 'group' => 'pos', 'description' => 'Can access POS interface'],
            ['name' => 'pos.create_sale', 'display_name' => 'Create Sales', 'group' => 'pos', 'description' => 'Can create sales transactions'],
            ['name' => 'pos.void_sale', 'display_name' => 'Void Sales', 'group' => 'pos', 'description' => 'Can void sales transactions'],
            ['name' => 'pos.apply_discount', 'display_name' => 'Apply Discounts', 'group' => 'pos', 'description' => 'Can apply discounts to sales'],

            // Payment Methods (Phase 3)
            ['name' => 'payment_methods.view', 'display_name' => 'View Payment Methods', 'group' => 'payment_methods', 'description' => 'Can view payment methods'],
            ['name' => 'payment_methods.create', 'display_name' => 'Create Payment Methods', 'group' => 'payment_methods', 'description' => 'Can create payment methods'],
            ['name' => 'payment_methods.update', 'display_name' => 'Update Payment Methods', 'group' => 'payment_methods', 'description' => 'Can update payment methods'],
            ['name' => 'payment_methods.delete', 'display_name' => 'Delete Payment Methods', 'group' => 'payment_methods', 'description' => 'Can delete payment methods'],

            // Offline Sync (Phase 3)
            ['name' => 'offline.sync', 'display_name' => 'Offline Sync', 'group' => 'offline', 'description' => 'Can sync offline sales'],
            ['name' => 'offline.resolve_conflicts', 'display_name' => 'Resolve Conflicts', 'group' => 'offline', 'description' => 'Can resolve offline sync conflicts'],

            // Purchases (Phase 4 - placeholder)
            ['name' => 'purchases.view', 'display_name' => 'View Purchases', 'group' => 'purchases', 'description' => 'Can view purchase orders'],
            ['name' => 'purchases.create', 'display_name' => 'Create Purchases', 'group' => 'purchases', 'description' => 'Can create purchase orders'],
            ['name' => 'purchases.manage', 'display_name' => 'Manage Purchases', 'group' => 'purchases', 'description' => 'Can manage purchase orders (edit, send, cancel)'],
            ['name' => 'purchases.receive', 'display_name' => 'Receive Goods', 'group' => 'purchases', 'description' => 'Can receive goods from suppliers'],
            ['name' => 'purchases.return', 'display_name' => 'Supplier Returns', 'group' => 'purchases', 'description' => 'Can create and manage supplier returns'],

            // Suppliers (Phase 4)
            ['name' => 'suppliers.view', 'display_name' => 'View Suppliers', 'group' => 'suppliers', 'description' => 'Can view suppliers'],
            ['name' => 'suppliers.manage', 'display_name' => 'Manage Suppliers', 'group' => 'suppliers', 'description' => 'Can create, update, and delete suppliers'],

            // Reports (Phase 5 - placeholder)
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'group' => 'reports', 'description' => 'Can view reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'group' => 'reports', 'description' => 'Can export reports'],

            // Reconciliation (Phase 5)
            ['name' => 'reconciliation.manage', 'display_name' => 'Manage Reconciliation', 'group' => 'reconciliation', 'description' => 'Can manage stock conflicts and reconciliation'],

            // System Management
            ['name' => 'system.manage', 'display_name' => 'Manage System', 'group' => 'system', 'description' => 'Can manage system updates, backups, and cache'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}

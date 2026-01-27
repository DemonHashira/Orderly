<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Guard for API authentication
        $guard = 'sanctum';

        // Roles
        $roles = [
            'Administrator',
            'Order Manager',
            'Warehouse Manager',
            'Inventory Manager',
        ];

        // Permission vocabulary
        $permissions = [
            // Orders
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.status.confirm',
            'orders.status.ready_to_ship',
            'orders.status.cancel',

            // Shipments
            'shipments.view',
            'shipments.create',
            'shipments.update',
            'shipments.outcome.delivered',
            'shipments.outcome.returned',
            'shipments.outcome.unpaid',

            // Customers
            'customers.view',
            'customers.manage',

            // Products
            'products.view',
            'products.manage',

            // Inventory
            'inventory.view',
            'inventory.movement.create',
            'inventory.report.view',
            'inventory.return_restock.approve',

            // Returns
            'returns.view',
            'returns.create',

            // Users
            'users.manage',
            'roles.manage',

            // Reporting
            'reports.view',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, $guard);
        }

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        // Permission sets per role
        $all = $permissions;

        $orderManager = [
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.status.confirm',
            'orders.status.ready_to_ship',
            'orders.status.cancel',

            'customers.view',
            'customers.manage',

            'products.view',

            'inventory.view',

            'shipments.view',

            'returns.view',
            'reports.view',
        ];

        $warehouseManager = [
            'orders.view',

            'shipments.view',
            'shipments.create',
            'shipments.update',
            'shipments.outcome.delivered',
            'shipments.outcome.returned',
            'shipments.outcome.unpaid',

            'products.view',
            'returns.view',
        ];

        $inventoryManager = [
            'inventory.view',
            'inventory.movement.create',
            'inventory.report.view',
            'inventory.return_restock.approve',

            'products.view',
            'orders.view',

            'returns.view',
            'returns.create',

            'reports.view',
        ];

        Role::findByName('Administrator', $guard)->syncPermissions($all);
        Role::findByName('Order Manager', $guard)->syncPermissions($orderManager);
        Role::findByName('Warehouse Manager', $guard)->syncPermissions($warehouseManager);
        Role::findByName('Inventory Manager', $guard)->syncPermissions($inventoryManager);
    }
}

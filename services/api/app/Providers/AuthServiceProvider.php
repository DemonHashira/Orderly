<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\InventoryStockPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReturnOrderPolicy;
use App\Policies\ShipmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Customer::class => CustomerPolicy::class,
        Order::class => OrderPolicy::class,
        Product::class => ProductPolicy::class,
        Shipment::class => ShipmentPolicy::class,
        ReturnOrder::class => ReturnOrderPolicy::class,
        InventoryStock::class => InventoryStockPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('reports.view', fn (User $user): bool => $this->hasPermission($user, 'reports.view'));
        Gate::define('dashboard.view', fn (User $user): bool => $this->hasPermission($user, 'dashboard.view'));
        Gate::define('reports.orders.view', fn (User $user): bool => $this->hasPermission($user, 'reports.orders.view'));
        Gate::define('reports.inventory.view', fn (User $user): bool => $this->hasPermission($user, 'reports.inventory.view'));
        Gate::define('reports.returns.view', fn (User $user): bool => $this->hasPermission($user, 'reports.returns.view'));
        Gate::define('products.import', fn (User $user): bool => $this->hasPermission($user, 'products.import'));
        Gate::define('products.export', fn (User $user): bool => $this->hasPermission($user, 'products.export'));
    }

    private function hasPermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission, 'web');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}

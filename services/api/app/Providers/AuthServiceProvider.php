<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\ReturnOrder;
use App\Models\Shipment;
use App\Policies\CustomerPolicy;
use App\Policies\InventoryStockPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ReturnOrderPolicy;
use App\Policies\ShipmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Customer::class => CustomerPolicy::class,
        Order::class => OrderPolicy::class,
        Shipment::class => ShipmentPolicy::class,
        ReturnOrder::class => ReturnOrderPolicy::class,
        InventoryStock::class => InventoryStockPolicy::class,
    ];
}

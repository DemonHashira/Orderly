<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['organization_id', 'created_at'], 'orders_org_created_at_idx');
            $table->index(['organization_id', 'current_status'], 'orders_org_status_idx');
        });

        Schema::table('return_orders', function (Blueprint $table): void {
            $table->index(['order_id', 'returned_at', 'created_at'], 'return_orders_order_returned_created_idx');
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->index(['return_id', 'restockable'], 'return_items_return_restockable_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->index(['organization_id', 'created_at'], 'inventory_movements_org_created_at_idx');
        });

        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->index(['organization_id', 'qty_on_hand', 'reorder_threshold'], 'inventory_stocks_org_qty_threshold_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropIndex('inventory_stocks_org_qty_threshold_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_org_created_at_idx');
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropIndex('return_items_return_restockable_idx');
        });

        Schema::table('return_orders', function (Blueprint $table): void {
            $table->dropIndex('return_orders_order_returned_created_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_org_status_idx');
            $table->dropIndex('orders_org_created_at_idx');
        });
    }
};

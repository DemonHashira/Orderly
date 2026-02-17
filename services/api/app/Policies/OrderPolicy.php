<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class OrderPolicy
{
    private string $guard = 'web';

    private function sameOrg(User $user, Order $order): bool
    {
        return (int) $user->organization_id === (int) $order->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->sameOrg($user, $order) && $this->hasPermission($user, 'orders.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        return $this->sameOrg($user, $order) && $this->hasPermission($user, 'orders.update');
    }

    public function confirm(User $user, Order $order): bool
    {
        return $this->sameOrg($user, $order) && $this->hasPermission($user, 'orders.status.confirm');
    }

    public function readyToShip(User $user, Order $order): bool
    {
        return $this->sameOrg($user, $order) && $this->hasPermission($user, 'orders.status.ready_to_ship');
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->sameOrg($user, $order) && $this->hasPermission($user, 'orders.status.cancel');
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->sameOrg($user, $order) && $this->hasPermission($user, 'orders.delete');
    }

    private function hasPermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission, $this->guard);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}

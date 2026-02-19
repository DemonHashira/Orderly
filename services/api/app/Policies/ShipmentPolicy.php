<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class ShipmentPolicy
{
    private string $guard = 'web';

    private function sameOrg(User $user, Shipment $shipment): bool
    {
        $shipment->loadMissing('order');

        return (int) $user->organization_id === (int) $shipment->order->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'shipments.view');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $this->hasPermission($user, 'shipments.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'shipments.create');
    }

    public function markDelivered(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $this->hasPermission($user, 'shipments.outcome.delivered');
    }

    public function markReturned(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $this->hasPermission($user, 'shipments.outcome.returned');
    }

    public function markUnpaid(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $this->hasPermission($user, 'shipments.outcome.unpaid');
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

<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

final class ShipmentPolicy
{
    private function sameOrg(User $user, Shipment $shipment): bool
    {
        $shipment->loadMissing('order');

        return (int) $user->organization_id === (int) $shipment->order->organization_id;
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $user->can('shipments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('shipments.create');
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $user->can('shipments.update');
    }

    public function markDelivered(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $user->can('shipments.outcome.delivered');
    }

    public function markReturned(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $user->can('shipments.outcome.returned');
    }

    public function markUnpaid(User $user, Shipment $shipment): bool
    {
        return $this->sameOrg($user, $shipment) && $user->can('shipments.outcome.unpaid');
    }
}

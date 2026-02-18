<?php

namespace App\Policies;

use App\Models\InventoryStock;
use App\Models\User;

final class InventoryStockPolicy
{
    private function sameOrg(User $user, InventoryStock $stock): bool
    {
        return (int) $user->organization_id === (int) $stock->organization_id;
    }

    public function view(User $user, InventoryStock $stock): bool
    {
        return $this->sameOrg($user, $stock) && $user->can('inventory.view');
    }

    public function createMovement(User $user, InventoryStock $stock): bool
    {
        return $this->sameOrg($user, $stock) && $user->can('inventory.movement.create');
    }

    public function viewReports(User $user, InventoryStock $stock): bool
    {
        return $this->sameOrg($user, $stock) && $user->can('inventory.report.view');
    }
}

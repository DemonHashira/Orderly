<?php

namespace App\Policies;

use App\Models\InventoryStock;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class InventoryStockPolicy
{
    private string $guard = 'web';

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'inventory.view');
    }

    private function sameOrg(User $user, InventoryStock $stock): bool
    {
        return (int) $user->organization_id === (int) $stock->organization_id;
    }

    public function view(User $user, InventoryStock $stock): bool
    {
        return $this->sameOrg($user, $stock) && $this->hasPermission($user, 'inventory.view');
    }

    public function createMovement(User $user, InventoryStock $stock): bool
    {
        return $this->sameOrg($user, $stock) && $this->hasPermission($user, 'inventory.movement.create');
    }

    public function viewReports(User $user, InventoryStock $stock): bool
    {
        return $this->sameOrg($user, $stock) && $this->hasPermission($user, 'inventory.report.view');
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

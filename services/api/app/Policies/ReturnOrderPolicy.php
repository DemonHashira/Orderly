<?php

namespace App\Policies;

use App\Models\ReturnOrder;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class ReturnOrderPolicy
{
    private string $guard = 'web';

    private function sameOrg(User $user, ReturnOrder $returnOrder): bool
    {
        $returnOrder->loadMissing('order');

        return (int) $user->organization_id === (int) $returnOrder->order->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'returns.view');
    }

    public function view(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->sameOrg($user, $returnOrder) && $this->hasPermission($user, 'returns.view');
    }

    public function addItem(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->sameOrg($user, $returnOrder) && $this->hasPermission($user, 'returns.item.add');
    }

    public function restock(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->sameOrg($user, $returnOrder) && $this->hasPermission($user, 'returns.restock');
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

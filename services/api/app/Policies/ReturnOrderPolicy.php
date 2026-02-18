<?php

namespace App\Policies;

use App\Models\ReturnOrder;
use App\Models\User;

final class ReturnOrderPolicy
{
    private function sameOrg(User $user, ReturnOrder $returnOrder): bool
    {
        $returnOrder->loadMissing('order');

        return (int) $user->organization_id === (int) $returnOrder->order->organization_id;
    }

    public function view(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->sameOrg($user, $returnOrder) && $user->can('returns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('returns.create');
    }

    public function approveRestock(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->sameOrg($user, $returnOrder) && $user->can('inventory.return_restock.approve');
    }
}

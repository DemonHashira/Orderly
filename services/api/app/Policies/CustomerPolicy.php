<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

final class CustomerPolicy
{
    private string $guard = 'web';

    private function sameOrg(User $user, Customer $customer): bool
    {
        return (int) $user->organization_id === (int) $customer->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('customers.view', $this->guard);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->sameOrg($user, $customer) && (
            $user->hasPermissionTo('customers.view', $this->guard)
            || $user->hasPermissionTo('customers.update', $this->guard)
            || $user->hasPermissionTo('customers.delete', $this->guard)
        );
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('customers.create', $this->guard);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->sameOrg($user, $customer) && $user->hasPermissionTo('customers.update', $this->guard);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->sameOrg($user, $customer) && $user->hasPermissionTo('customers.delete', $this->guard);
    }
}

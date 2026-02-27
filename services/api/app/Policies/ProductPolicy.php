<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class ProductPolicy
{
    private string $guard = 'web';

    private function sameOrg(User $user, Product $product): bool
    {
        return (int) $user->organization_id === (int) $product->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->sameOrg($user, $product) && $this->hasPermission($user, 'products.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->sameOrg($user, $product) && $this->hasPermission($user, 'products.manage');
    }

    public function archive(User $user, Product $product): bool
    {
        return $this->sameOrg($user, $product) && $this->hasPermission($user, 'products.manage');
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

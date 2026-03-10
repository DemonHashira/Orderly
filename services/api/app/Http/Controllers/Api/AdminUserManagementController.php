<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminRolesRequest;
use App\Http\Requests\Admin\IndexAdminUsersRequest;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRoleRequest;
use App\Http\Requests\Admin\UpdateAdminUserStatusRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class AdminUserManagementController extends Controller
{
    private const array ALLOWED_ROLES = [
        'Owner',
        'Order Manager',
        'Logistics Manager',
        'Inventory Manager',
    ];

    private const string GUARD_NAME = 'web';

    public function indexUsers(IndexAdminUsersRequest $request): AnonymousResourceCollection
    {
        $organizationId = (int) $request->user()->organization_id;
        $query = User::query()
            ->where('organization_id', $organizationId)
            ->with('roles')
            ->orderByDesc('id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(middle_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return AdminUserResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function storeUser(StoreAdminUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = isset($data['role']) ? trim((string) $data['role']) : null;

        if ($role !== null && $role !== '') {
            Gate::authorize('roles.manage');
        }

        $user = DB::transaction(function () use ($request, $data, $role): User {
            $createdUser = User::query()->create([
                'organization_id' => (int) $request->user()->organization_id,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($role !== null && $role !== '') {
                $roleModel = Role::findByName($role, self::GUARD_NAME);
                $createdUser->syncRoles([$roleModel]);
            }

            return $createdUser;
        });

        return new AdminUserResource($user->load('roles'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateUser(UpdateAdminUserRequest $request, int $user): AdminUserResource
    {
        $userModel = $this->findOrganizationUser($request->user()->organization_id, $user);
        $data = $request->validated();

        $payload = [
            'first_name' => array_key_exists('first_name', $data) ? $data['first_name'] : $userModel->first_name,
            'middle_name' => array_key_exists('middle_name', $data) ? $data['middle_name'] : $userModel->middle_name,
            'last_name' => array_key_exists('last_name', $data) ? $data['last_name'] : $userModel->last_name,
            'email' => array_key_exists('email', $data) ? $data['email'] : $userModel->email,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $userModel->forceFill($payload)->save();

        return new AdminUserResource($userModel->refresh()->load('roles'));
    }

    public function updateUserStatus(
        UpdateAdminUserStatusRequest $request,
        int $user,
    ): AdminUserResource|JsonResponse {
        $actor = $request->user();
        $userModel = $this->findOrganizationUser($actor->organization_id, $user);
        $isActive = (bool) $request->validated('is_active');

        if ((int) $actor->id === (int) $userModel->id && ! $isActive) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $isActive && $this->isLastActiveOwner($userModel)) {
            return response()->json([
                'message' => 'You cannot deactivate the last active Owner.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userModel->forceFill([
            'is_active' => $isActive,
        ])->save();

        return new AdminUserResource($userModel->refresh()->load('roles'));
    }

    public function indexRoles(IndexAdminRolesRequest $request): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', self::GUARD_NAME)
            ->whereIn('name', self::ALLOWED_ROLES)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name): array => ['name' => $name])
            ->values();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function updateUserRole(
        UpdateAdminUserRoleRequest $request,
        int $user,
    ): AdminUserResource|JsonResponse {
        $actor = $request->user();
        $userModel = $this->findOrganizationUser($actor->organization_id, $user);
        $nextRole = (string) $request->validated('role');

        if ((int) $actor->id === (int) $userModel->id && $nextRole !== 'Owner' && $this->isLastActiveOwner($userModel)) {
            return response()->json([
                'message' => 'You cannot remove Owner role from the last active Owner.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roleModel = Role::findByName($nextRole, self::GUARD_NAME);
        $userModel->syncRoles([$roleModel]);

        return new AdminUserResource($userModel->refresh()->load('roles'));
    }

    private function findOrganizationUser(int $organizationId, int $userId): User
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($userId);
    }

    private function isLastActiveOwner(User $user): bool
    {
        if (! $user->is_active || ! $user->hasRole('Owner', self::GUARD_NAME)) {
            return false;
        }

        $activeOwnersCount = User::query()
            ->where('organization_id', (int) $user->organization_id)
            ->where('is_active', true)
            ->role('Owner', self::GUARD_NAME)
            ->count();

        return $activeOwnersCount <= 1;
    }
}

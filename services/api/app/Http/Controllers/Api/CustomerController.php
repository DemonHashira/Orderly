<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);

        $orgId = (int) $request->user()->organization_id;

        $query = Customer::query()->forOrg($orgId);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(middle_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$needle]);
            });
        }

        $email = trim((string) $request->query('email', ''));
        if ($email !== '') {
            $query->where('email', 'like', '%'.$email.'%');
        }

        $phone = trim((string) $request->query('phone', ''));
        if ($phone !== '') {
            $query->where('phone', 'like', '%'.$phone.'%');
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return CustomerResource::collection(
            $query->latest('id')->paginate($perPage)->withQueryString(),
        );
    }

    public function show(Request $request, int $customer): CustomerResource
    {
        $customerModel = Customer::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($customer);

        Gate::authorize('view', $customerModel);

        return new CustomerResource($customerModel);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::query()->create(array_merge(
            $request->validated(),
            ['organization_id' => (int) $request->user()->organization_id],
        ));

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCustomerRequest $request, int $customer): CustomerResource
    {
        $customerModel = Customer::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($customer);

        Gate::authorize('update', $customerModel);

        $customerModel->update($request->validated());

        return new CustomerResource($customerModel);
    }

    public function destroy(Request $request, int $customer): Response
    {
        $customerModel = Customer::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($customer);

        Gate::authorize('delete', $customerModel);

        $customerModel->delete();

        return response()->noContent();
    }
}

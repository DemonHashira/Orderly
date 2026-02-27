<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesChannels\IndexSalesChannelsRequest;
use App\Http\Resources\SalesChannelResource;
use App\Models\SalesChannel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SalesChannelController extends Controller
{
    public function index(IndexSalesChannelsRequest $request): AnonymousResourceCollection
    {
        $query = SalesChannel::query();

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';

            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
            });
        }

        return SalesChannelResource::collection(
            $query->orderBy('name')->get(),
        );
    }

    public function show(int $salesChannel): SalesChannelResource
    {
        return new SalesChannelResource(
            SalesChannel::query()->findOrFail($salesChannel),
        );
    }
}

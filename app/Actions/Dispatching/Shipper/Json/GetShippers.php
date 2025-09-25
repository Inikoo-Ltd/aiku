<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipper\Json;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Dispatching\ShippersResource;
use App\Models\Dispatching\Shipper;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class GetShippers extends OrgAction
{
    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        // return Shipper::where('organisation_id', $organisation->id)
        //     ->where('status', true)
        //     ->orderBy('api_shipper')
        //     ->get();

        return $this->handle($organisation);
    }

    
    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('shippers.name', $value)
                    ->orWhereStartWith('shippers.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Shipper::class);
        $queryBuilder->where('organisation_id', $organisation->id)
            ->where('status', true);

        return $queryBuilder
            ->defaultSort('code')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse($shipper): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\Resources\Json\JsonResource
    {
        return ShippersResource::collection($shipper);
    }
}

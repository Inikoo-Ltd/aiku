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
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Dispatching\ShippersResource;
use App\Models\Dispatching\Shipper;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class GetShippers extends OrgAction
{
    /**
     * Shippers are read by the organisation's dispatching staff, and by order staff of its shops,
     * who add shipments from the order page.
     */
    public function authorize(ActionRequest $request): bool
    {
        $permissionNames = WarehousePermissionsEnum::getDispatchingViewPermissionNames($this->organisation);

        foreach ($this->organisation->shops()->pluck('id') as $shopId) {
            $permissionNames[] = "orders.$shopId.edit";
        }

        return $request->user()->authTo($permissionNames);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
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

        if (!$this->isDispatchSupervisor($organisation)) {
            $queryBuilder->whereRaw("coalesce((settings->>'supervisor_only')::boolean, false) = false");
        }

        return $queryBuilder
            ->defaultSort('code')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    /**
     * PRE-73. Supervisor-only shippers are left out of everybody else's list.
     */
    private function isDispatchSupervisor(Organisation $organisation): bool
    {
        $user = request()->user();
        if (!$user) {
            return true;
        }

        return $user->authTo([
            "org-admin.$organisation->id",
            ...$organisation->warehouses()->pluck('id')->map(fn (int $warehouseId) => "supervisor-dispatching.$warehouseId")->all(),
        ]);
    }

    public function jsonResponse($shipper): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\Resources\Json\JsonResource
    {
        return ShippersResource::collection($shipper);
    }
}

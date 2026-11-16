<?php

/*
 * Author Louis Perez
 * Created on 09-11-2026-11h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileWarehouses
{
    use AsController;

    public function handle(User $user): array
    {
        return $user->authorisedWarehouses()
            ->with('organisation')
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $warehouse) => [
                'id'           => $warehouse->id,
                'slug'         => $warehouse->slug,
                'code'         => $warehouse->code,
                'name'         => $warehouse->name,
                'organisation' => [
                    'id'   => $warehouse->organisation->id,
                    'code' => $warehouse->organisation->code,
                    'slug' => $warehouse->organisation->slug,
                    'name' => $warehouse->organisation->name,
                ],
                'can'          => $this->abilities($user, $warehouse),
            ])
            ->all();
    }

    public function abilities(User $user, Warehouse $warehouse): array
    {
        $warehouseId    = $warehouse->id;
        $organisationId = $warehouse->organisation_id;

        return [
            'view_locations'      => $user->authTo(["locations.$warehouseId.view", "supervisor-locations.$warehouseId"]),
            'edit_locations'      => $user->authTo(["locations.$warehouseId.edit", "supervisor-locations.$warehouseId"]),
            'view_inventory'      => $user->authTo(["inventory.$organisationId.view", "accounting.$organisationId.view", "supervisor-stocks.$warehouseId.view", "stocks.$warehouseId.view"]),
            'move_stock'          => $user->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($warehouse->organisation)),
            'view_goods_in'       => $user->authTo(["incoming.$warehouseId.view", "supervisor-incoming.$warehouseId"]),
            'book_in'             => $user->authTo(["incoming.$warehouseId.edit", "supervisor-incoming.$warehouseId", "procurement.$organisationId.edit"]),
            'supervise_goods_in'  => $user->authTo(["supervisor-incoming.$warehouseId", "procurement.$organisationId.edit"]),
            'edit_procurement'    => $user->authTo("procurement.$organisationId.edit"),
            'place_stock_deliveries' => $user->authTo(["procurement.$organisationId.edit", "accounting.$organisationId.edit", "org-supervisor.$organisationId.accounting"]),
            'view_goods_out'      => $user->authTo(["dispatching.$warehouseId.view", "fulfilment.$warehouseId.view", "supervisor-dispatching.$warehouseId"]),
            'pick_and_pack'       => $user->authTo(["dispatching.$warehouseId.edit", "supervisor-dispatching.$warehouseId"]),
            'supervise_goods_out' => $user->authTo("supervisor-dispatching.$warehouseId"),
            'view_fulfilment'     => $user->authTo(["fulfilment.$warehouseId.view", "supervisor-fulfilment.$warehouseId"]),
            'edit_fulfilment'     => $user->authTo(["fulfilment.$warehouseId.edit", "supervisor-fulfilment.$warehouseId"]),
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->handle($user)]);
    }
}

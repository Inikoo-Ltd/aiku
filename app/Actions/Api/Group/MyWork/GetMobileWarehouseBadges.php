<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-14h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\MyWork;

use App\Actions\Api\Group\GoodsIn\IndexMobileStockDeliveries;
use App\Actions\Api\Group\Inbox\WithMobileInbox;
use App\Actions\Api\Group\WithMobileModuleAccess;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileWarehouseBadges
{
    use AsController;
    use WithMobileMyWork;
    use WithMobileInbox;
    use WithMobileModuleAccess;

    /**
     * @return array{my_work: int, goods_out_unassigned: int|null, goods_in_received: int|null, inbox_unread: int}
     */
    public function handle(Warehouse $warehouse, User $user): array
    {
        $visibleSources = $this->visibleSources($user, $warehouse);

        return [
            'my_work'              => $this->myWorkCounts($warehouse, $user, $visibleSources)['total'],
            'goods_out_unassigned' => $visibleSources['delivery_notes'] ? $this->goodsOutUnassignedCount($warehouse) : null,
            'goods_in_received'    => self::canViewGoodsIn($user, $warehouse) ? IndexMobileStockDeliveries::numberInState($warehouse, StockDeliveryStateEnum::RECEIVED->value) : null,
            'inbox_unread'         => $this->inboxUnreadCount($user),
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureAuthorisedWarehouse($user, $warehouse);

        return response()->json(['data' => $this->handle($warehouse, $user)]);
    }
}

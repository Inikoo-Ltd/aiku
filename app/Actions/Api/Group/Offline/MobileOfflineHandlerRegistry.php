<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\AuditLocationOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\AuditOrgStockOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\MoveStockOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\NotPickedOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\PackOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\PickOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\PlaceStockDeliveryItemOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\ReturnDamagedOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\ReturnNotReturnedOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\ReturnPutBackOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\SetWaitingCrmOfflineHandler;
use App\Actions\Api\Group\Offline\Handlers\SetWaitingWarehouseOfflineHandler;

class MobileOfflineHandlerRegistry
{
    /**
     * @var array<string, class-string<MobileOfflineHandler>>
     */
    public const array HANDLERS = [
        'pick'                      => PickOfflineHandler::class,
        'not_picked'                => NotPickedOfflineHandler::class,
        'set_waiting_warehouse'     => SetWaitingWarehouseOfflineHandler::class,
        'set_waiting_crm'           => SetWaitingCrmOfflineHandler::class,
        'pack'                      => PackOfflineHandler::class,
        'move_stock'                => MoveStockOfflineHandler::class,
        'place_stock_delivery_item' => PlaceStockDeliveryItemOfflineHandler::class,
        'return_put_back'           => ReturnPutBackOfflineHandler::class,
        'return_damaged'            => ReturnDamagedOfflineHandler::class,
        'return_not_returned'       => ReturnNotReturnedOfflineHandler::class,
        'audit_location'            => AuditLocationOfflineHandler::class,
        'audit_org_stock'           => AuditOrgStockOfflineHandler::class,
    ];

    /**
     * @var array<string, MobileOfflineHandler>
     */
    private array $overrides = [];

    public function for(string $operation): ?MobileOfflineHandler
    {
        if (isset($this->overrides[$operation])) {
            return $this->overrides[$operation];
        }

        $class = self::HANDLERS[$operation] ?? null;

        return $class ? app($class) : null;
    }

    public function swap(string $operation, MobileOfflineHandler $handler): void
    {
        $this->overrides[$operation] = $handler;
    }

    /**
     * @return array<int, string>
     */
    public function operations(): array
    {
        return array_keys(self::HANDLERS);
    }
}

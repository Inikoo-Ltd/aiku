<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-11h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Dispatching\Picking\UndoSetAsWaitingWarehouse;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;

class SetWaitingWarehouseOfflineHandler implements MobileOfflineHandler
{
    use WithOfflineLineWaiting;

    public function operation(): string
    {
        return 'set_waiting_warehouse';
    }

    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        return $this->applyWaiting($context, $payload, true);
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return $this->rollbackLine($operation) !== null;
    }

    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        $deliveryNoteItem = $this->rollbackLine($operation);
        if (!$deliveryNoteItem) {
            $this->refuseRollback(__('This waiting can not be undone any more: the waiting on the line was changed or cleared.'));
        }

        UndoSetAsWaitingWarehouse::run($deliveryNoteItem);
    }

    private function rollbackLine(MobileOfflineOperation $operation): ?DeliveryNoteItem
    {
        $effect           = $this->updatedEffect($operation, 'DeliveryNoteItem');
        $deliveryNoteItem = $this->operationLine($operation);

        if (!$effect || !$deliveryNoteItem || (float) data_get($effect->before, 'quantity_waiting_warehouse', 0) > 0) {
            return null;
        }

        $isUnchanged = round((float) $deliveryNoteItem->quantity_waiting_warehouse, 6) === round((float) data_get($effect->after, 'quantity_waiting_warehouse'), 6);
        $clearedAt   = $deliveryNoteItem->getRawOriginal('waiting_warehouse_cleared_at');
        $isCleared   = $clearedAt && CarbonImmutable::parse($clearedAt)->gt($operation->synced_at);
        $isPicking   = in_array($deliveryNoteItem->deliveryNote?->state, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED], true);

        return $isUnchanged && !$isCleared && $isPicking ? $deliveryNoteItem : null;
    }
}

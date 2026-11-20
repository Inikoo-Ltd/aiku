<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-11h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemPacking;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemUnpack;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Packing;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class PackOfflineHandler implements MobileOfflineHandler
{
    use WithOfflineDeliveryNoteLine;

    public function operation(): string
    {
        return 'pack';
    }

    public function validatePayload(array $payload): array
    {
        return $this->validateLinePayload($payload, [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ]);
    }

    /**
     * @throws Throwable
     */
    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        $requested = (float) $payload['quantity'];
        if ($done = $this->alreadyDone($context, $payload)) {
            return $done;
        }

        $deliveryNoteItem = $this->lockedLine($context, $payload);
        $refusal          = $this->lineRefusal($context, $deliveryNoteItem, [DeliveryNoteStateEnum::PACKING], MobileOfflineOutcome::INSTRUCTION_UNPACK, $requested);
        if ($refusal) {
            return $refusal;
        }

        $leftToPack = UpdateDeliveryNoteItemPacking::quantityLeftToPack($deliveryNoteItem);
        if ($leftToPack <= 0) {
            return MobileOfflineOutcome::refused('over_packed', MobileOfflineOutcome::INSTRUCTION_UNPACK, __('Nothing is left to pack on this line. Unpack :quantity.', [
                'quantity' => $this->quantityLabel($deliveryNoteItem, $requested),
            ]), $requested, null, $this->lineState($deliveryNoteItem));
        }

        $recorded    = min($requested, $leftToPack);
        $before      = $this->lineState($deliveryNoteItem);
        $lastPacking = (int) $deliveryNoteItem->packings()->max('id');

        try {
            UpdateDeliveryNoteItemPacking::make()->action($deliveryNoteItem, $context->user(), $recorded);
        } catch (Throwable $exception) {
            if (!$this->isKnownRefusal($exception)) {
                throw $exception;
            }

            if ($this->newPackings($deliveryNoteItem, $lastPacking)->isEmpty()) {
                return $this->refusalFromException($exception, MobileOfflineOutcome::INSTRUCTION_UNPACK, $requested);
            }
        }

        foreach ($this->newPackings($deliveryNoteItem, $lastPacking) as $packing) {
            $context->recordCreated($packing);
        }
        $context->recordUpdated($deliveryNoteItem, $before, $this->lineState($deliveryNoteItem));
        $this->markDone($context, $payload);

        if (round($recorded, 6) < round($requested, 6)) {
            return MobileOfflineOutcome::partlyApplied(
                $requested,
                $recorded,
                'over_packed',
                MobileOfflineOutcome::INSTRUCTION_UNPACK,
                __('Only :recorded was left to pack on this line. Unpack the other :rest.', [
                    'recorded' => $this->quantityLabel($deliveryNoteItem, $recorded),
                    'rest'     => $this->quantityLabel($deliveryNoteItem, $requested - $recorded),
                ])
            );
        }

        return MobileOfflineOutcome::applied($requested, $recorded, false, __(':quantity packed.', ['quantity' => $this->quantityLabel($deliveryNoteItem, $recorded)]));
    }

    /**
     * @return Collection<int, Packing>
     */
    private function newPackings(DeliveryNoteItem $deliveryNoteItem, int $lastPacking): Collection
    {
        return $deliveryNoteItem->packings()->where('id', '>', $lastPacking)->orderBy('id')->get();
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return $this->rollbackLine($operation) !== null;
    }

    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        $deliveryNoteItem = $this->rollbackLine($operation);
        if (!$deliveryNoteItem) {
            $this->refuseRollback(__('This pack can not be undone any more: the delivery note moved on or the line was packed again.'));
        }

        UpdateDeliveryNoteItemUnpack::make()->action($deliveryNoteItem, $user);
    }

    private function rollbackLine(MobileOfflineOperation $operation): ?DeliveryNoteItem
    {
        $effect           = $this->createdEffect($operation, 'Packing');
        $deliveryNoteItem = $this->operationLine($operation);

        if (!$effect || !$deliveryNoteItem) {
            return null;
        }

        $packingIds = $deliveryNoteItem->packings()->pluck('id')->all();
        $isPacking  = in_array($deliveryNoteItem->deliveryNote?->state, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED], true);

        return $isPacking && $packingIds === [(int) $effect->model_id] ? $deliveryNoteItem : null;
    }
}

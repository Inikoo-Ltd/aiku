<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-10h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Dispatching\Picking\DeletePicking;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use RuntimeException;

class NotPickedOfflineHandler implements MobileOfflineHandler
{
    use WithOfflineDeliveryNoteLine;

    public function operation(): string
    {
        return 'not_picked';
    }

    public function validatePayload(array $payload): array
    {
        return $this->validateLinePayload($payload, [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'reason'   => ['required', Rule::enum(PickingNotPickedReasonEnum::class)],
            'note'     => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
    }

    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        $requested = (float) $payload['quantity'];
        if ($done = $this->alreadyDone($context, $payload)) {
            return $done;
        }

        $deliveryNoteItem = $this->lockedLine($context, $payload);
        $refusal          = $this->lineRefusal($context, $deliveryNoteItem, [DeliveryNoteStateEnum::HANDLING], MobileOfflineOutcome::INSTRUCTION_NONE, $requested, 'allow_picker_set_not_picked');
        if ($refusal) {
            return $refusal;
        }

        $outstanding = StoreNotPickPicking::make()->outstandingQuantity($deliveryNoteItem);
        if ($outstanding <= 0) {
            return MobileOfflineOutcome::refused('over_required', MobileOfflineOutcome::INSTRUCTION_NONE, __('Nothing is left to mark as not picked on this line.'), $requested, null, $this->lineState($deliveryNoteItem));
        }

        $recorded = min($requested, $outstanding);
        $before   = $this->lineState($deliveryNoteItem);

        $picking = StoreNotPickPicking::run($deliveryNoteItem, $context->user(), array_filter([
            'quantity'          => $recorded,
            'not_picked_reason' => $payload['reason'],
            'not_picked_note'   => $payload['note'] ?? null,
        ], fn ($value) => $value !== null));

        if (!$picking) {
            throw new RuntimeException('The line is busy, the not picked mark was not recorded');
        }

        $context->recordCreated($picking);
        $context->recordUpdated($deliveryNoteItem, $before, $this->lineState($deliveryNoteItem));
        $this->markDone($context, $payload);

        $quantity = $this->quantityLabel($deliveryNoteItem, $recorded);
        if (round($recorded, 6) < round($requested, 6)) {
            return MobileOfflineOutcome::partlyApplied(
                $requested,
                $recorded,
                'over_required',
                MobileOfflineOutcome::INSTRUCTION_NONE,
                __('Only :quantity was left to mark as not picked on this line.', ['quantity' => $quantity])
            );
        }

        return MobileOfflineOutcome::applied($requested, $recorded, false, __(':quantity marked as not picked.', ['quantity' => $quantity]));
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return $this->rollbackPicking($operation) !== null;
    }

    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        $picking = $this->rollbackPicking($operation);
        if (!$picking) {
            $this->refuseRollback(__('This not picked mark can not be undone any more: the delivery note moved on or the mark was changed.'));
        }

        DeletePicking::make()->action($picking, $user);
    }

    private function rollbackPicking(MobileOfflineOperation $operation): ?Picking
    {
        $effect  = $this->createdEffect($operation, 'Picking');
        $picking = $effect ? Picking::find($effect->model_id) : null;

        if (!$picking || $picking->type !== PickingTypeEnum::NOT_PICK || round((float) $picking->quantity, 6) !== round((float) $operation->applied_quantity, 6)) {
            return null;
        }

        return in_array($picking->deliveryNote?->state, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED], true) ? $picking : null;
    }
}

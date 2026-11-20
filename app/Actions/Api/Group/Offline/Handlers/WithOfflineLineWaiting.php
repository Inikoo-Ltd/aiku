<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-11h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Dispatching\Picking\SetAsWaitingCrm;
use App\Actions\Dispatching\Picking\SetAsWaitingWarehouse;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

trait WithOfflineLineWaiting
{
    use WithOfflineDeliveryNoteLine;

    public function validatePayload(array $payload): array
    {
        return $this->validateLinePayload($payload, [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'note'     => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws Throwable
     */
    protected function applyWaiting(MobileOfflineContext $context, array $payload, bool $forWarehouse): MobileOfflineOutcome
    {
        $requested = (float) $payload['quantity'];
        if ($done = $this->alreadyDone($context, $payload)) {
            return $done;
        }

        $deliveryNoteItem = $this->lockedLine($context, $payload);
        $refusal          = $this->lineRefusal($context, $deliveryNoteItem, [DeliveryNoteStateEnum::HANDLING], MobileOfflineOutcome::INSTRUCTION_NONE, $requested, 'allow_waiting');
        if ($refusal) {
            return $refusal;
        }

        if ($clearedAt = $this->waitingClearedAfter($deliveryNoteItem, $context->performedAt())) {
            return MobileOfflineOutcome::refused(
                'waiting_cleared',
                MobileOfflineOutcome::INSTRUCTION_PICK_AGAIN,
                __('The waiting on this line was cleared at :time: pick it again.', ['time' => $this->localTime($clearedAt, $context->organisation())]),
                $requested,
                null,
                $this->lineState($deliveryNoteItem)
            );
        }

        $before    = $this->lineState($deliveryNoteItem);
        $modelData = array_filter([
            'quantity' => $requested,
            'note'     => $payload['note'] ?? null,
        ], fn ($value) => $value !== null);

        try {
            DB::transaction(fn () => $forWarehouse
                ? SetAsWaitingWarehouse::make()->action($deliveryNoteItem, $context->user(), $modelData)
                : SetAsWaitingCrm::make()->action($deliveryNoteItem, $context->user(), $modelData));
        } catch (Throwable $exception) {
            if (!$this->isKnownRefusal($exception)) {
                throw $exception;
            }

            $refusal = $this->refusalFromException($exception, MobileOfflineOutcome::INSTRUCTION_NONE, $requested);

            return $refusal->reason === 'no_right'
                ? MobileOfflineOutcome::refused('setting_off', MobileOfflineOutcome::INSTRUCTION_NONE, __('Waiting is not enabled for this organisation.'), $requested)
                : MobileOfflineOutcome::refused('over_required', MobileOfflineOutcome::INSTRUCTION_NONE, __('Nothing is left to set as waiting: the rest of the line is already picked or with customer services.'), $requested, null, $this->lineState($deliveryNoteItem));
        }

        $after    = $this->lineState($deliveryNoteItem);
        $recorded = $forWarehouse
            ? $after['quantity_waiting_warehouse']
            : $after['quantity_waiting_crm'] - $before['quantity_waiting_crm'];

        $context->recordUpdated($deliveryNoteItem, $before, $after);
        $this->markDone($context, $payload);

        $quantity = $this->quantityLabel($deliveryNoteItem, $recorded);
        if (round($recorded, 6) < round($requested, 6)) {
            return MobileOfflineOutcome::partlyApplied(
                $requested,
                $recorded,
                'over_required',
                MobileOfflineOutcome::INSTRUCTION_NONE,
                $forWarehouse
                    ? __('Only :quantity was left to set as waiting for the warehouse on this line.', ['quantity' => $quantity])
                    : __('Only :quantity was left to pass to customer services on this line.', ['quantity' => $quantity])
            );
        }

        return MobileOfflineOutcome::applied($requested, $recorded, false, $forWarehouse
            ? __(':quantity set as waiting for the warehouse.', ['quantity' => $quantity])
            : __(':quantity passed to customer services.', ['quantity' => $quantity]));
    }

    protected function waitingClearedAfter(DeliveryNoteItem $deliveryNoteItem, CarbonImmutable $performedAt): ?CarbonImmutable
    {
        $clearedAt = $deliveryNoteItem->getRawOriginal('waiting_warehouse_cleared_at');
        if (!$clearedAt) {
            return null;
        }

        $clearedAt = CarbonImmutable::parse($clearedAt);

        return $clearedAt->gt($performedAt) ? $clearedAt : null;
    }
}

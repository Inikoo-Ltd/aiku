<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-10h-35m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Dispatching\Picking\DeletePicking;
use App\Actions\Dispatching\Picking\PickingRefusedException;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class PickOfflineHandler implements MobileOfflineHandler
{
    use WithOfflineDeliveryNoteLine;

    public function operation(): string
    {
        return 'pick';
    }

    public function validatePayload(array $payload): array
    {
        return $this->validateLinePayload($payload, [
            'location_org_stock_id' => ['required', 'integer', 'min:1'],
            'quantity'              => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ]);
    }

    public function touchedRows(array $payload): array
    {
        return [
            ['t' => 'DeliveryNoteItem', 'id' => (int) $payload['delivery_note_item_id']],
            ['t' => 'LocationOrgStock', 'id' => (int) $payload['location_org_stock_id']],
        ];
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
        $refusal          = $this->lineRefusal($context, $deliveryNoteItem, [DeliveryNoteStateEnum::HANDLING], MobileOfflineOutcome::INSTRUCTION_PUT_BACK, $requested);
        if ($refusal) {
            return $refusal;
        }

        /** @var LocationOrgStock|null $locationOrgStock */
        $locationOrgStock = LocationOrgStock::whereKey((int) $payload['location_org_stock_id'])->lockForUpdate()->first();
        if ($refusal = $this->locationRefusal($deliveryNoteItem, $locationOrgStock, $requested)) {
            return $refusal;
        }

        $countedSince  = $context->countedSince($locationOrgStock);
        $before        = $this->lineState($deliveryNoteItem);
        $lastMovement  = (int) OrgStockMovement::where('org_stock_id', $locationOrgStock->org_stock_id)->max('id');
        $locationCode  = (string) $locationOrgStock->location?->code;

        try {
            $picking = DB::transaction(function () use ($context, $deliveryNoteItem, $locationOrgStock, $requested, $countedSince) {
                $storePicking = StorePicking::make()->writingStockMovementNow()->refusingShortStock();
                if ($countedSince) {
                    $storePicking->withLocationCountedSince();
                }

                return $storePicking->action($deliveryNoteItem, $context->user(), [
                    'location_org_stock_id' => $locationOrgStock->id,
                    'quantity'              => $requested,
                    'picker_user_id'        => $context->user()->id,
                ]);
            });
        } catch (PickingRefusedException $exception) {
            return $this->pickRefusal($context, $exception, $deliveryNoteItem, $locationOrgStock, $requested);
        } catch (Throwable $exception) {
            if ($this->isKnownRefusal($exception)) {
                return $this->refusalFromException($exception, MobileOfflineOutcome::INSTRUCTION_PUT_BACK, $requested);
            }

            throw $exception;
        }

        $this->recordEffects($context, $picking, $deliveryNoteItem, $before, $locationOrgStock, $lastMovement);
        $this->markDone($context, $payload);

        $recorded = (float) $picking->quantity;
        if (round($recorded, 6) < round($requested, 6)) {
            return MobileOfflineOutcome::partlyApplied(
                $requested,
                $recorded,
                'over_required',
                MobileOfflineOutcome::INSTRUCTION_PUT_BACK,
                __('The line needed only :recorded. Put the other :rest back at :location.', [
                    'recorded' => $this->quantityLabel($deliveryNoteItem, $recorded),
                    'rest'     => $this->quantityLabel($deliveryNoteItem, $requested - $recorded),
                    'location' => $locationCode,
                ]),
                $countedSince,
                $this->otherPicker($deliveryNoteItem, $context->user())?->id
            );
        }

        return MobileOfflineOutcome::applied($requested, $recorded, $countedSince, __('Picked :quantity at :location.', [
            'quantity' => $this->quantityLabel($deliveryNoteItem, $recorded),
            'location' => $locationCode,
        ]));
    }

    private function locationRefusal(DeliveryNoteItem $deliveryNoteItem, ?LocationOrgStock $locationOrgStock, float $requested): ?MobileOfflineOutcome
    {
        if (!$locationOrgStock || $locationOrgStock->warehouse_id !== $deliveryNoteItem->deliveryNote->warehouse_id || $locationOrgStock->org_stock_id !== $deliveryNoteItem->org_stock_id) {
            return MobileOfflineOutcome::refused('not_found', MobileOfflineOutcome::INSTRUCTION_PUT_BACK, __('That location does not hold this item.'), $requested);
        }

        return null;
    }

    private function pickRefusal(MobileOfflineContext $context, PickingRefusedException $exception, DeliveryNoteItem $deliveryNoteItem, LocationOrgStock $locationOrgStock, float $requested): MobileOfflineOutcome
    {
        $locationCode = (string) $locationOrgStock->location?->code;
        $quantity     = $this->quantityLabel($deliveryNoteItem, $requested);

        if ($exception->reason === PickingRefusedException::NOTHING_LEFT_TO_PICK) {
            $otherPicking = $this->otherPicking($deliveryNoteItem, $context->user());

            return MobileOfflineOutcome::refused(
                'over_required',
                MobileOfflineOutcome::INSTRUCTION_PUT_BACK,
                $otherPicking?->picker
                    ? __(':name picked this line at :time. Put your :quantity back at :location.', [
                        'name'     => $otherPicking->picker->contact_name ?? $otherPicking->picker->username,
                        'time'     => $this->localTime($otherPicking->created_at, $context->organisation()),
                        'quantity' => $quantity,
                        'location' => $locationCode,
                    ])
                    : __('Nothing is left to pick on this line. Put your :quantity back at :location.', ['quantity' => $quantity, 'location' => $locationCode]),
                $requested,
                $otherPicking?->picker_user_id,
                $this->lineState($deliveryNoteItem)
            );
        }

        $context->requestStockCheck($locationOrgStock, __('An offline pick of :quantity :code from :location for :reference could not be recorded: Aiku shows :stock there. Count the location so the pick can be applied.', [
            'quantity'  => $quantity,
            'code'      => (string) $deliveryNoteItem->orgStock?->code,
            'location'  => $locationCode,
            'reference' => $deliveryNoteItem->deliveryNote->reference,
            'stock'     => $this->quantityLabel($deliveryNoteItem, (float) $locationOrgStock->quantity),
        ]));

        return MobileOfflineOutcome::refused(
            $exception->reason,
            MobileOfflineOutcome::INSTRUCTION_KEEP_IN_TOTE,
            $exception->reason === PickingRefusedException::LOCATION_EMPTY
                ? __('Aiku shows no stock at :location. Keep the :quantity in the tote: a stock check was requested.', ['location' => $locationCode, 'quantity' => $quantity])
                : __('Aiku shows only :stock at :location. Keep the :quantity in the tote: a stock check was requested.', [
                    'stock'    => $this->quantityLabel($deliveryNoteItem, (float) $locationOrgStock->quantity),
                    'location' => $locationCode,
                    'quantity' => $quantity,
                ]),
            $requested,
            null,
            ['location_org_stock_id' => $locationOrgStock->id, 'quantity' => (float) $locationOrgStock->quantity]
        );
    }

    /**
     * @param array<string, mixed> $before
     */
    private function recordEffects(MobileOfflineContext $context, Picking $picking, DeliveryNoteItem $deliveryNoteItem, array $before, LocationOrgStock $locationOrgStock, int $lastMovement): void
    {
        $context->recordCreated($picking);

        OrgStockMovement::where('org_stock_id', $locationOrgStock->org_stock_id)
            ->where('location_id', $locationOrgStock->location_id)
            ->where('id', '>', $lastMovement)
            ->orderBy('id')
            ->get()
            ->each(fn (OrgStockMovement $orgStockMovement) => $context->recordCreated($orgStockMovement));

        $context->recordUpdated($deliveryNoteItem, $before, $this->lineState($deliveryNoteItem));
    }

    private function otherPicking(DeliveryNoteItem $deliveryNoteItem, User $user): ?Picking
    {
        return $deliveryNoteItem->pickings()
            ->whereIn('type', [PickingTypeEnum::PICK, PickingTypeEnum::MAGIC_PICK])
            ->where('picker_user_id', '!=', $user->id)
            ->with('picker')
            ->orderByDesc('id')
            ->first();
    }

    private function otherPicker(DeliveryNoteItem $deliveryNoteItem, User $user): ?User
    {
        return $this->otherPicking($deliveryNoteItem, $user)?->picker;
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return $this->rollbackPicking($operation) !== null;
    }

    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        $picking = $this->rollbackPicking($operation);
        if (!$picking) {
            $this->refuseRollback(__('This pick can not be undone any more: the delivery note moved on or the pick was changed.'));
        }

        DeletePicking::make()->action($picking, $user);
    }

    private function rollbackPicking(MobileOfflineOperation $operation): ?Picking
    {
        $effect  = $this->createdEffect($operation, 'Picking');
        $picking = $effect ? Picking::find($effect->model_id) : null;

        if (!$picking || round((float) $picking->quantity, 6) !== round((float) $operation->applied_quantity, 6)) {
            return null;
        }

        $state = $picking->deliveryNote?->state;

        return in_array($state, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED], true) ? $picking : null;
    }
}

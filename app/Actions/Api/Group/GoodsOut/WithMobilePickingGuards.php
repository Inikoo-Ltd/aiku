<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\WithScannedDeliveryNoteItemMatching;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Dispatching\Shipment;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait WithMobilePickingGuards
{
    use WithMobilePickingActions;
    use WithScannedDeliveryNoteItemMatching;

    protected function ensureCanView(DeliveryNote $deliveryNote, User $user): void
    {
        abort_unless($user->authorisedWarehouses()->whereKey($deliveryNote->warehouse_id)->exists(), 404);
        abort_unless($deliveryNote->canBeViewedBy($user), 403);
    }

    protected function ensureCanWork(DeliveryNote $deliveryNote, User $user): void
    {
        $this->ensureCanView($deliveryNote, $user);
        abort_unless($deliveryNote->canBeWorkedOnBy($user), 403, __('Only warehouse staff can work on this delivery note.'));
        $this->ensureNotLockedInAurora($deliveryNote);
    }

    protected function ensureCanShip(DeliveryNote $deliveryNote, User $user): void
    {
        $this->ensureCanView($deliveryNote, $user);
        abort_unless($deliveryNote->canBeShippedBy($user), 403, __('You can not add or change the shipments of this delivery note.'));
        $this->ensureNotLockedInAurora($deliveryNote);
    }

    protected function ensureNotLockedInAurora(DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->isLockedInAurora()) {
            throw ValidationException::withMessages(['delivery_note' => ShowDeliveryNote::make()->getAuroraNotice($deliveryNote)]);
        }
    }

    protected function ensureActionAvailable(DeliveryNote $deliveryNote, User $user, string $key, bool $refuseDisabled = true): void
    {
        $action = collect($this->availableActions(
            $deliveryNote,
            $user,
            ShowDeliveryNote::make()->isEditableInWarehouse($deliveryNote, $user),
            $this->canHandleDeliveryNote($deliveryNote)
        ))->firstWhere('key', $key);

        if (!$action) {
            $this->refuseState(__('This can not be done on this delivery note right now.'));
        }

        if ($refuseDisabled && isset($action['disabled_reason'])) {
            $this->refuseState($action['disabled_reason']);
        }
    }

    protected function ensureShipmentOfNote(DeliveryNote $deliveryNote, Shipment $shipment): void
    {
        abort_unless($deliveryNote->shipments()->whereKey($shipment->id)->exists(), 404);
    }

    /**
     * @param array<int, DeliveryNoteStateEnum> $states
     */
    protected function ensureState(DeliveryNote $deliveryNote, array $states): void
    {
        if (in_array($deliveryNote->state, $states, true)) {
            return;
        }

        $this->refuseState($states === [DeliveryNoteStateEnum::HANDLING]
            ? __('This delivery note is not being picked.')
            : __('This can not be done while the delivery note is :state.', ['state' => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value]]));
    }

    protected function ensureNotInPickingSession(DeliveryNote $deliveryNote): void
    {
        $pickingSession = $deliveryNote->pickingSessions()->orderBy('picking_sessions.id')->first();

        if ($pickingSession) {
            $this->refuseState(__('This delivery note is in picking session :reference.', ['reference' => $pickingSession->reference]));
        }
    }

    protected function refuseState(string $message): never
    {
        throw ValidationException::withMessages(['state' => $message]);
    }

    protected function ensureCanHandle(DeliveryNote $deliveryNote): void
    {
        abort_unless($this->canHandleDeliveryNote($deliveryNote), 403, __('Somebody else is handling this delivery note. Take it over first.'));
    }

    protected function ensureLineAction(DeliveryNote $deliveryNote, User $user): void
    {
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING]);
        $this->ensureCanHandle($deliveryNote);
    }

    protected function ensureLineOfNote(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem): void
    {
        abort_unless($deliveryNoteItem->delivery_note_id === $deliveryNote->id, 404);
    }

    /**
     * @param array<int, array<string, mixed>> $lineLocations
     */
    protected function pickableLocation(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, int $locationOrgStockId, array $lineLocations): LocationOrgStock
    {
        /** @var LocationOrgStock|null $locationOrgStock */
        $locationOrgStock = LocationOrgStock::find($locationOrgStockId);
        abort_unless($locationOrgStock && $locationOrgStock->warehouse_id === $deliveryNote->warehouse_id, 404);

        if ($locationOrgStock->org_stock_id !== $deliveryNoteItem->org_stock_id) {
            throw ValidationException::withMessages(['location_org_stock_id' => __('That location does not hold this item.')]);
        }

        $isLineLocation = collect($lineLocations)->contains('location_org_stock_id', $locationOrgStock->id);
        if (!$isLineLocation && !$this->pickingSettings($deliveryNote->organisation)['allow_picker_choose_location']) {
            throw ValidationException::withMessages(['location_org_stock_id' => __('Pick from one of the locations shown for this line.')]);
        }

        return $locationOrgStock;
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    protected function passingWebRefusals(Closure $callback, string $errorKey): mixed
    {
        try {
            return $callback();
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            throw ValidationException::withMessages([$errorKey => $exception->getMessage()]);
        }
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    protected function passingShipmentRefusals(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $key          = match ((string) $key) {
                    'tracking_number' => 'tracking',
                    '0'               => 'message',
                    default           => (string) $key,
                };
                $errors[$key] = array_merge($errors[$key] ?? [], (array) $messages);
            }

            throw ValidationException::withMessages($errors);
        }
    }

    protected function stateReachedMessage(DeliveryNote $deliveryNote): string
    {
        $deliveryNote->refresh();

        return match ($deliveryNote->state) {
            DeliveryNoteStateEnum::DISPATCHED => $deliveryNote->collection_address_id ? __('Set as collected.') : __('Delivery note dispatched.'),
            DeliveryNoteStateEnum::FINALISED  => __('Delivery note finalised.'),
            DeliveryNoteStateEnum::PACKED     => __('Delivery note packed.'),
            default                           => __('The delivery note is :state.', ['state' => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value]]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function noteSnapshot(DeliveryNote $deliveryNote): array
    {
        $deliveryNote = DeliveryNote::findOrFail($deliveryNote->id);

        return [
            'state'          => $deliveryNote->state->value,
            'picker_user_id' => $deliveryNote->picker_user_id,
            'trolleys'       => $deliveryNote->trolleys()->orderBy('trolleys.id')->pluck('trolleys.id')->all(),
            'picked_bays'    => $deliveryNote->pickedBays()->orderBy('picked_bays.id')->pluck('picked_bays.id')->all(),
            'lines'          => $deliveryNote->deliveryNoteItems()->orderBy('id')->get()->map(fn (DeliveryNoteItem $deliveryNoteItem) => $this->lineQuantities($deliveryNoteItem))->all(),
            'pickings'       => Picking::where('delivery_note_id', $deliveryNote->id)->orderBy('id')->get(['id', 'quantity'])->map(fn (Picking $picking) => [$picking->id, (float) $picking->quantity])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lineSnapshot(DeliveryNoteItem $deliveryNoteItem): array
    {
        $deliveryNoteItem = DeliveryNoteItem::findOrFail($deliveryNoteItem->id);

        return [
            ...$this->lineQuantities($deliveryNoteItem),
            'pickings' => $deliveryNoteItem->pickings()->orderBy('id')->get(['id', 'quantity'])->map(fn (Picking $picking) => [$picking->id, (float) $picking->quantity])->all(),
        ];
    }

    /**
     * @return array<string, float|bool>
     */
    private function lineQuantities(DeliveryNoteItem $deliveryNoteItem): array
    {
        return [
            'quantity_picked'            => (float) $deliveryNoteItem->quantity_picked,
            'quantity_not_picked'        => (float) $deliveryNoteItem->quantity_not_picked,
            'quantity_waiting_warehouse' => (float) $deliveryNoteItem->quantity_waiting_warehouse,
            'quantity_waiting_crm'       => (float) $deliveryNoteItem->quantity_waiting_crm,
            'is_handled'                 => (bool) $deliveryNoteItem->is_handled,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param array<string, mixed> $extra
     */
    protected function respond(DeliveryNote $deliveryNote, User $user, array $before, array $after, string $doneMessage, string $nothingChangedMessage, array $extra = []): JsonResponse
    {
        $changed = $before !== $after;

        return $this->respondWith($deliveryNote, $user, $changed ? 'done' : 'nothing_changed', $changed ? $doneMessage : $nothingChangedMessage, $extra);
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function respondWith(DeliveryNote $deliveryNote, User $user, string $status, ?string $message, array $extra = []): JsonResponse
    {
        return response()->json([
            'data'   => app(GetMobileDeliveryNote::class)->handle(DeliveryNote::findOrFail($deliveryNote->id), $user),
            'result' => array_merge(['status' => $status, 'message' => $message], $extra),
        ]);
    }
}

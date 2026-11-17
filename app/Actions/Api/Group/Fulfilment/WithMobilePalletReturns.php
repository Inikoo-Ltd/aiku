<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Api\Group\GoodsOut\WithMobilePrinters;
use App\Enums\Fulfilment\PalletReturn\PalletReturnAttachmentScopeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait WithMobilePalletReturns
{
    use WithMobilePalletReturnLines;
    use WithMobilePalletReturnShipments;
    use WithMobilePrinters;

    protected function ensureCanViewPalletReturn(User $user, Warehouse $warehouse, PalletReturn $palletReturn): void
    {
        $this->ensureCanViewFulfilment($user, $warehouse);
        abort_unless($palletReturn->warehouse_id === $warehouse->id, 404);
    }

    protected function ensureCanWorkPalletReturn(User $user, Warehouse $warehouse, PalletReturn $palletReturn): void
    {
        $this->ensureCanViewPalletReturn($user, $warehouse, $palletReturn);
        abort_unless(self::canEditFulfilmentWarehouse($user, $warehouse), 403, __('You can not work on the returns of this warehouse.'));
    }

    protected function ensureCanHandlePalletReturn(User $user, Warehouse $warehouse, PalletReturn $palletReturn): void
    {
        $this->ensureCanViewPalletReturn($user, $warehouse, $palletReturn);
        abort_unless(self::canEditFulfilmentWarehouseOrShop($user, $warehouse, $palletReturn->fulfilment_id), 403, __('You can not change this return.'));
    }

    protected function ensureLineOfReturn(PalletReturn $palletReturn, PalletReturnItem $palletReturnItem): void
    {
        abort_unless($palletReturnItem->pallet_return_id === $palletReturn->id, 404);
    }

    protected function isInPickingSession(PalletReturn $palletReturn): bool
    {
        return $palletReturn->items()->whereNotNull('picking_session_id')->exists();
    }

    protected function changePalletReturn(PalletReturn $palletReturn, Closure $change): mixed
    {
        return $this->underLock('pallet_return', $palletReturn->id, function () use ($palletReturn, $change) {
            $palletReturn = PalletReturn::findOrFail($palletReturn->id);

            if ($this->isInPickingSession($palletReturn)) {
                $this->refuseState(__('This return is in a picking session; work it on the web.'));
            }

            return $change($palletReturn);
        });
    }

    /**
     * @param array<int, PalletReturnStateEnum> $states
     */
    protected function ensureReturnState(PalletReturn $palletReturn, array $states): void
    {
        if (!in_array($palletReturn->state, $states, true)) {
            $this->refuseState(__('This can not be done while the return is :state.', ['state' => PalletReturnStateEnum::labels()[$palletReturn->state->value]]));
        }
    }

    protected function lineReadyFor(PalletReturn $palletReturn, PalletReturnItem $palletReturnItem, string $action): PalletReturnItem
    {
        $this->ensureReturnState($palletReturn, [PalletReturnStateEnum::PICKING]);

        $line = PalletReturnItem::with(['pallet.location', 'palletStoredItem', 'storedItem'])->findOrFail($palletReturnItem->id);

        if (!in_array($action, $this->lineActionKeys($line), true)) {
            $this->refuseState(match (true) {
                (bool) $line->has_waiting_crm                                                                   => __('This line is waiting for customer service.'),
                $action === 'undo'                                                                              => __('There is nothing to undo on this line.'),
                $action === 'pick' && $line->type === 'StoredItem' && $line->getQuantityLeftToPick() > 0        => __('There is no stock of this item left on this pallet; set the rest as not picked.'),
                default                                                                                         => __('There is nothing left to pick on this line.'),
            });
        }

        return $line;
    }

    protected function shipmentRequired(PalletReturn $palletReturn): bool
    {
        return Arr::get($palletReturn->fulfilment->shop->settings, 'dispatch.require_shipping', true) && !$palletReturn->is_collection;
    }

    /**
     * @return array{total: int, unprinted: int}
     */
    protected function insertCounts(PalletReturn $palletReturn): array
    {
        return [
            'total'     => $palletReturn->leaflets()->printable()->count(),
            'unprinted' => $palletReturn->unprintedLeaflets()->count(),
        ];
    }

    protected function dispatchBlocked(PalletReturn $palletReturn): ?string
    {
        if ($palletReturn->state !== PalletReturnStateEnum::PICKED) {
            return null;
        }

        if ($palletReturn->hasUnprintedLeaflets()) {
            return __('Print the customer inserts first.');
        }

        if ($this->shipmentRequired($palletReturn)
            && !$palletReturn->shipments()->exists()
            && !$palletReturn->attachments()->wherePivot('scope', PalletReturnAttachmentScopeEnum::SHIPPING_LABEL->value)->exists()) {
            return __('Add a shipment first.');
        }

        return null;
    }

    protected function canScanToPick(Warehouse $warehouse, PalletReturn $palletReturn, User $user, bool $inPickingSession): bool
    {
        return !$inPickingSession
            && $palletReturn->state === PalletReturnStateEnum::PICKING
            && $this->scanToPickEnabled($warehouse)
            && self::canEditFulfilmentWarehouse($user, $warehouse);
    }

    protected function scanToPickEnabled(Warehouse $warehouse): bool
    {
        return (bool) data_get($warehouse->organisation->settings, 'orders.allow_scan_to_pick', false);
    }

    /**
     * @param Collection<int, PalletReturnItem> $lines
     * @param array{total: int, unprinted: int} $inserts
     * @return array<int, array{key: string, label: string}>
     */
    protected function palletReturnActions(Warehouse $warehouse, PalletReturn $palletReturn, User $user, Collection $lines, array $inserts, ?string $dispatchBlocked, bool $inPickingSession): array
    {
        if ($inPickingSession) {
            return [];
        }

        $canHandle        = self::canEditFulfilmentWarehouseOrShop($user, $warehouse, $palletReturn->fulfilment_id);
        $canWorkWarehouse = self::canEditFulfilmentWarehouse($user, $warehouse);
        $state            = $palletReturn->state;

        return array_values(array_filter([
            $canHandle && $state === PalletReturnStateEnum::CONFIRMED ? $this->actionOption('start_picking', __('Start picking')) : null,
            $canHandle && $state === PalletReturnStateEnum::PICKING && $this->finishPickingBlocked($palletReturn, $lines) === null ? $this->actionOption('finish_picking', __('Set as picked')) : null,
            $canHandle && $state === PalletReturnStateEnum::PICKED && $dispatchBlocked === null ? $this->actionOption('dispatch', $palletReturn->is_collection ? __('Set as collected') : __('Dispatch')) : null,
            $canWorkWarehouse && in_array($state, [PalletReturnStateEnum::PICKING, PalletReturnStateEnum::PICKED], true) && !$palletReturn->is_collection ? $this->actionOption('add_shipment', __('Add shipment')) : null,
            $canHandle && $inserts['unprinted'] > 0 && $this->insertPrinterId($user) !== null ? $this->actionOption('print_inserts', __('Print customer inserts')) : null,
        ]));
    }

    /**
     * @return array<int, array{state: string, label: string, at: string|null}>
     */
    protected function palletReturnTimeline(PalletReturn $palletReturn): array
    {
        $states = [PalletReturnStateEnum::CONFIRMED, PalletReturnStateEnum::PICKING, PalletReturnStateEnum::PICKED, PalletReturnStateEnum::DISPATCHED];
        if ($palletReturn->state === PalletReturnStateEnum::CANCEL) {
            $states[] = PalletReturnStateEnum::CANCEL;
        }

        return array_map(fn (PalletReturnStateEnum $state) => [
            'state' => $state->value,
            'label' => PalletReturnStateEnum::labels()[$state->value],
            'at'    => $this->isoDateTime($palletReturn->{$state->snake().'_at'}),
        ], $states);
    }

    /**
     * @return array<string, mixed>
     */
    protected function palletReturnDetail(Warehouse $warehouse, PalletReturn $palletReturn, User $user): array
    {
        $palletReturn->loadMissing(['fulfilmentCustomer.customer', 'platform', 'pickerUser', 'packerUser', 'fulfilment.shop']);

        $inPickingSession = $this->isInPickingSession($palletReturn);
        $lines            = $this->returnLines($palletReturn);
        $isPallets        = $this->typeGroup($palletReturn->type) === 'pallets';
        $linesEditable    = !$inPickingSession && $palletReturn->state === PalletReturnStateEnum::PICKING && self::canEditFulfilmentWarehouse($user, $warehouse);
        $inserts          = $this->insertCounts($palletReturn);
        $dispatchBlocked  = $this->dispatchBlocked($palletReturn);

        return [
            'id'                      => $palletReturn->id,
            'reference'               => $palletReturn->reference,
            'customer_reference'      => $palletReturn->customer_reference,
            'type'                    => $palletReturn->type->value,
            'type_group'              => $this->typeGroup($palletReturn->type),
            'type_label'              => $this->typeGroupLabel($palletReturn->type),
            'platform'                => $palletReturn->platform ? ['code' => $palletReturn->platform->code, 'name' => $palletReturn->platform->name] : null,
            'state'                   => $palletReturn->state->value,
            'state_label'             => PalletReturnStateEnum::labels()[$palletReturn->state->value],
            'customer'                => $this->customerReference($palletReturn->fulfilmentCustomer),
            'is_collection'           => (bool) $palletReturn->is_collection,
            'collection_notes'        => $palletReturn->collection_notes,
            'customer_notes'          => $palletReturn->customer_notes,
            'public_notes'            => $palletReturn->public_notes,
            'internal_notes'          => $palletReturn->internal_notes,
            'picker'                  => $this->userReference($palletReturn->pickerUser),
            'packer'                  => $this->userReference($palletReturn->packerUser),
            'estimated_delivery_date' => $palletReturn->estimated_delivery_date ? Carbon::parse($palletReturn->estimated_delivery_date)->toDateString() : null,
            'in_picking_session'      => $inPickingSession,
            'timeline'                => $this->palletReturnTimeline($palletReturn),
            'counts'                  => $this->lineCounts($lines),
            'pallets'                 => $isPallets ? $this->palletLineRows($lines, $linesEditable) : [],
            'stored_items'            => $isPallets ? [] : $this->storedItemRows($warehouse, $palletReturn, $lines, $linesEditable),
            'inserts'                 => $inserts,
            'shipments'               => $this->shipmentRows($warehouse, $palletReturn, $user),
            'parcels'                 => $this->parcelRows($palletReturn),
            'shipment_required'       => $this->shipmentRequired($palletReturn),
            'dispatch_blocked'        => $dispatchBlocked,
            'can_scan'                => $this->canScanToPick($warehouse, $palletReturn, $user, $inPickingSession),
            'available_actions'       => $this->palletReturnActions($warehouse, $palletReturn, $user, $lines, $inserts, $dispatchBlocked, $inPickingSession),
        ];
    }

    /**
     * @param array<string, mixed> $result
     */
    protected function respondWithPalletReturn(Warehouse $warehouse, PalletReturn $palletReturn, User $user, string $status, string $message, array $result = []): JsonResponse
    {
        return response()->json([
            'data'   => $this->palletReturnDetail($warehouse, PalletReturn::findOrFail($palletReturn->id), $user),
            'result' => [
                'status'  => $status,
                'message' => $message,
                ...$result,
            ],
        ]);
    }
}

<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait WithMobilePalletDeliveries
{
    use WithMobileStoredPallets;

    public const array ACTIVE_PALLET_DELIVERY_STATES = [
        PalletDeliveryStateEnum::CONFIRMED,
        PalletDeliveryStateEnum::RECEIVED,
        PalletDeliveryStateEnum::BOOKING_IN,
    ];

    public const array NOT_YET_BOOKED_IN_PALLET_STATES = [
        PalletStateEnum::IN_PROCESS,
        PalletStateEnum::SUBMITTED,
        PalletStateEnum::CONFIRMED,
        PalletStateEnum::RECEIVED,
        PalletStateEnum::BOOKING_IN,
        PalletStateEnum::NOT_RECEIVED,
    ];

    protected function ensureCanViewPalletDeliveries(User $user, Warehouse $warehouse): void
    {
        $this->ensureFulfilmentAccess($user, $warehouse, static::canViewFulfilment($user, $warehouse), __('You can not see the pallet deliveries of this warehouse.'));
    }

    protected function ensurePalletDeliveryOfWarehouse(PalletDelivery $palletDelivery, Warehouse $warehouse): void
    {
        abort_unless($palletDelivery->warehouse_id === $warehouse->id, 404);
    }

    protected function ensureCanViewPalletDelivery(User $user, Warehouse $warehouse, PalletDelivery $palletDelivery): void
    {
        $this->ensureCanViewPalletDeliveries($user, $warehouse);
        $this->ensurePalletDeliveryOfWarehouse($palletDelivery, $warehouse);
    }

    protected function ensureCanEditPalletDeliveryAsShop(User $user, Warehouse $warehouse, PalletDelivery $palletDelivery): void
    {
        $allowed = static::canEditFulfilmentShop($user, $palletDelivery->fulfilment_id);
        $refusal = __('You can not receive or book in the deliveries of this customer.');

        $this->ensureFulfilmentAccess($user, $warehouse, $allowed || static::canViewFulfilment($user, $warehouse), $refusal);
        $this->ensurePalletDeliveryOfWarehouse($palletDelivery, $warehouse);
        abort_unless($allowed, 403, $refusal);
    }

    protected function ensureCanBookInPallet(User $user, Warehouse $warehouse, Pallet $pallet): PalletDelivery
    {
        $allowed = static::canEditFulfilmentWarehouse($user, $warehouse);
        $refusal = __('You can not book in pallets in this warehouse.');

        $this->ensureFulfilmentAccess($user, $warehouse, $allowed || static::canViewFulfilment($user, $warehouse), $refusal);
        $this->ensurePalletOfWarehouse($pallet, $warehouse);

        $palletDelivery = $pallet->palletDelivery;
        abort_unless($palletDelivery && $palletDelivery->warehouse_id === $warehouse->id, 404);
        abort_unless($allowed, 403, $refusal);

        return $palletDelivery;
    }

    protected function refusePalletDeliveryState(PalletDelivery $palletDelivery): never
    {
        $this->refuseState(__('This can not be done while the delivery is :state.', ['state' => PalletDeliveryStateEnum::labels()[$palletDelivery->state->value]]));
    }

    /**
     * @return EloquentCollection<int, Pallet>
     */
    protected function palletDeliveryPallets(PalletDelivery $palletDelivery): EloquentCollection
    {
        $toBookIn = [PalletStateEnum::RECEIVED->value, PalletStateEnum::BOOKING_IN->value];

        return Pallet::query()
            ->where('pallet_delivery_id', $palletDelivery->id)
            ->with([...$this->palletRowRelations(), 'palletStoredItems.storedItem'])
            ->withCount('palletStoredItems')
            ->orderByRaw(
                'case when state in (?, ?) then 0 when state = ? then 1 when state = ? then 2 else 3 end',
                [...$toBookIn, PalletStateEnum::BOOKED_IN->value, PalletStateEnum::NOT_RECEIVED->value]
            )
            ->orderBy('reference')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param Collection<int, Pallet> $pallets
     */
    protected function finishBookingBlockedReason(Collection $pallets): ?string
    {
        $stillToBookIn = $pallets->reject(fn (Pallet $pallet) => in_array($pallet->state, [PalletStateEnum::BOOKED_IN, PalletStateEnum::NOT_RECEIVED], true))->count();
        if ($stillToBookIn > 0) {
            return trans_choice(':count pallet still to book in|:count pallets still to book in', $stillToBookIn);
        }

        $withoutRental = $pallets->filter(fn (Pallet $pallet) => $pallet->state === PalletStateEnum::BOOKED_IN && !$pallet->rental_id)->count();
        if ($withoutRental > 0) {
            return trans_choice(':count pallet has no rental; set it on the web|:count pallets have no rental; set it on the web', $withoutRental);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function palletDeliveryDetail(Warehouse $warehouse, PalletDelivery $palletDelivery, User $user): array
    {
        $palletDelivery->loadMissing('fulfilmentCustomer.customer');

        $pallets      = $this->palletDeliveryPallets($palletDelivery);
        $canEditShop  = static::canEditFulfilmentShop($user, $palletDelivery->fulfilment_id);
        $canBookIn    = static::canEditFulfilmentWarehouse($user, $warehouse);
        $isBookingIn  = $palletDelivery->state === PalletDeliveryStateEnum::BOOKING_IN;
        $blockedBy    = $isBookingIn ? $this->finishBookingBlockedReason($pallets) : null;
        $reporters    = $this->palletReporters($pallets);
        $deliveryRows = $pallets->map(fn (Pallet $pallet) => [
            ...$this->palletRow($pallet, $reporters),
            'has_rental'        => $pallet->rental_id !== null,
            'stored_items'      => $this->palletDeliveryStoredItems($pallet),
            'available_actions' => $canBookIn ? $this->palletInDeliveryActions($palletDelivery, $pallet) : [],
        ])->values()->all();

        return [
            'id'                      => $palletDelivery->id,
            'reference'               => $palletDelivery->reference,
            'customer_reference'      => $palletDelivery->customer_reference,
            'state'                   => $palletDelivery->state->value,
            'state_label'             => PalletDeliveryStateEnum::labels()[$palletDelivery->state->value],
            'customer'                => $this->fulfilmentCustomerIdAndName($palletDelivery->fulfilmentCustomer),
            'estimated_delivery_date' => $this->palletDeliveryDate($palletDelivery->estimated_delivery_date),
            'handing_type'            => $palletDelivery->handing_type,
            'customer_notes'          => $palletDelivery->customer_notes ?: null,
            'public_notes'            => $palletDelivery->public_notes ?: null,
            'internal_notes'          => $palletDelivery->internal_notes ?: null,
            'timeline'                => $this->palletDeliveryTimeline($palletDelivery),
            'counts'                  => $this->palletDeliveryCounts($pallets),
            'pallets'                 => $deliveryRows,
            'services'                => $this->palletDeliveryTransactions($palletDelivery, FulfilmentTransactionTypeEnum::SERVICE),
            'physical_goods'          => $this->palletDeliveryTransactions($palletDelivery, FulfilmentTransactionTypeEnum::PRODUCT),
            'finish_booking_blocked'  => $canEditShop ? $blockedBy : null,
            'available_actions'       => $canEditShop ? $this->palletDeliveryActions($palletDelivery, $isBookingIn && $blockedBy === null) : [],
        ];
    }

    protected function palletDeliveryDate(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    /**
     * @return array<int, array{state: string, label: string, at: string|null}>
     */
    protected function palletDeliveryTimeline(PalletDelivery $palletDelivery): array
    {
        $lastState = $palletDelivery->state === PalletDeliveryStateEnum::NOT_RECEIVED ? PalletDeliveryStateEnum::NOT_RECEIVED : PalletDeliveryStateEnum::BOOKED_IN;
        $states    = [PalletDeliveryStateEnum::CONFIRMED, PalletDeliveryStateEnum::RECEIVED, PalletDeliveryStateEnum::BOOKING_IN, $lastState];

        return collect($states)->map(fn (PalletDeliveryStateEnum $state) => [
            'state' => $state->value,
            'label' => PalletDeliveryStateEnum::labels()[$state->value],
            'at'    => $this->isoDateTime($palletDelivery->{$state->value.'_at'}),
        ])->all();
    }

    /**
     * @param Collection<int, Pallet> $pallets
     * @return array<string, int>
     */
    protected function palletDeliveryCounts(Collection $pallets): array
    {
        $ofType  = fn (PalletTypeEnum $type) => $pallets->filter(fn (Pallet $pallet) => $pallet->type === $type)->count();
        $inState = fn (PalletStateEnum $state) => $pallets->filter(fn (Pallet $pallet) => $pallet->state === $state)->count();

        return [
            'pallets'      => $ofType(PalletTypeEnum::PALLET),
            'boxes'        => $ofType(PalletTypeEnum::BOX),
            'oversizes'    => $ofType(PalletTypeEnum::OVERSIZE),
            'total'        => $pallets->count(),
            'received'     => $inState(PalletStateEnum::RECEIVED),
            'booking_in'   => $inState(PalletStateEnum::BOOKING_IN),
            'booked_in'    => $pallets->reject(fn (Pallet $pallet) => in_array($pallet->state, self::NOT_YET_BOOKED_IN_PALLET_STATES, true))->count(),
            'not_received' => $inState(PalletStateEnum::NOT_RECEIVED),
        ];
    }

    /**
     * @return array<int, array{id: int, reference: string|null, name: string|null, quantity: int}>
     */
    protected function palletDeliveryStoredItems(Pallet $pallet): array
    {
        return $pallet->palletStoredItems
            ->filter(fn (PalletStoredItem $palletStoredItem) => $palletStoredItem->storedItem !== null)
            ->sortBy(fn (PalletStoredItem $palletStoredItem) => [$palletStoredItem->storedItem->reference, $palletStoredItem->id])
            ->map(fn (PalletStoredItem $palletStoredItem) => [
                'id'        => $palletStoredItem->storedItem->id,
                'reference' => $palletStoredItem->storedItem->reference,
                'name'      => $palletStoredItem->storedItem->name,
                'quantity'  => (int) $palletStoredItem->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string|null, name: string|null, quantity: int, unit_label: string}>
     */
    protected function palletDeliveryTransactions(PalletDelivery $palletDelivery, FulfilmentTransactionTypeEnum $type): array
    {
        $unitLabel = $type === FulfilmentTransactionTypeEnum::SERVICE ? __('service') : __('unit');

        return FulfilmentTransaction::query()
            ->where('fulfilment_transactions.parent_type', class_basename($palletDelivery))
            ->where('fulfilment_transactions.parent_id', $palletDelivery->id)
            ->where('fulfilment_transactions.type', $type->value)
            ->leftJoin('historic_assets', 'historic_assets.id', '=', 'fulfilment_transactions.historic_asset_id')
            ->orderBy('historic_assets.code')
            ->orderBy('fulfilment_transactions.id')
            ->get(['historic_assets.code', 'historic_assets.name', 'fulfilment_transactions.quantity'])
            ->map(fn (FulfilmentTransaction $transaction) => [
                'code'       => $transaction->getAttribute('code'),
                'name'       => $transaction->getAttribute('name'),
                'quantity'   => (int) $transaction->quantity,
                'unit_label' => $unitLabel,
            ])
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function palletDeliveryActions(PalletDelivery $palletDelivery, bool $canFinishBooking): array
    {
        return match (true) {
            $palletDelivery->state === PalletDeliveryStateEnum::CONFIRMED => [$this->actionOption('receive', __('Mark as received'))],
            $palletDelivery->state === PalletDeliveryStateEnum::RECEIVED  => [$this->actionOption('start_booking', __('Start booking in'))],
            $canFinishBooking                                             => [$this->actionOption('finish_booking', __('Set as booked in'))],
            default                                                       => [],
        };
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function palletInDeliveryActions(PalletDelivery $palletDelivery, Pallet $pallet): array
    {
        $actions = [];

        if ($this->palletCanBeBookedIn($palletDelivery, $pallet)) {
            $actions[] = $this->actionOption('book_in', $pallet->state === PalletStateEnum::BOOKED_IN ? __('Change location') : __('Book in'));
        }

        if ($this->palletCanBeSetAsNotReceived($palletDelivery, $pallet)) {
            $actions[] = $this->actionOption('not_received', __('Not received'));
        }

        if ($this->palletNotReceivedCanBeUndone($palletDelivery, $pallet)) {
            $actions[] = $this->actionOption('undo_not_received', __('Undo not received'));
        }

        return $actions;
    }

    protected function palletCanBeBookedIn(PalletDelivery $palletDelivery, Pallet $pallet): bool
    {
        return $palletDelivery->state === PalletDeliveryStateEnum::BOOKING_IN
            && in_array($pallet->state, [PalletStateEnum::BOOKING_IN, PalletStateEnum::BOOKED_IN], true);
    }

    protected function palletCanBeSetAsNotReceived(PalletDelivery $palletDelivery, Pallet $pallet): bool
    {
        return in_array($palletDelivery->state, [PalletDeliveryStateEnum::RECEIVED, PalletDeliveryStateEnum::BOOKING_IN], true)
            && in_array($pallet->state, [PalletStateEnum::RECEIVED, PalletStateEnum::BOOKING_IN], true)
            && $pallet->location_id === null;
    }

    protected function palletNotReceivedCanBeUndone(PalletDelivery $palletDelivery, Pallet $pallet): bool
    {
        return $palletDelivery->state === PalletDeliveryStateEnum::RECEIVED
            && $pallet->state === PalletStateEnum::NOT_RECEIVED;
    }

    protected function respondWithPalletDelivery(Warehouse $warehouse, PalletDelivery $palletDelivery, User $user, string $status, string $message): JsonResponse
    {
        return response()->json([
            'data'   => $this->palletDeliveryDetail($warehouse, $palletDelivery, $user),
            'result' => ['status' => $status, 'message' => $message],
        ]);
    }
}

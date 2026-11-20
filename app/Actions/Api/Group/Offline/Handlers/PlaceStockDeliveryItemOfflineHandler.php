<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\GoodsIn\WithMobileStockDeliveries;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\GoodsIn\Sowing\DeleteSowing;
use App\Actions\GoodsIn\StockDeliveryItem\UpsertStockDeliveryItemPlaced;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlaceStockDeliveryItemOfflineHandler implements MobileOfflineHandler
{
    use WithMobileOfflineStockHandling;
    use WithMobileStockDeliveries;

    public const float TOLERANCE = 0.00005;

    public function operation(): string
    {
        return 'place_stock_delivery_item';
    }

    public function kind(): string
    {
        return self::KIND_RULES;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'stock_delivery_id'       => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'stock_delivery_item_id'  => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'quantity'                => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'location_org_stock_id'   => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID, 'required_without:location_id', 'prohibits:location_id'],
            'location_id'             => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID, 'required_without:location_org_stock_id'],
            'set_as_picking_location' => ['sometimes', 'boolean'],
            'client_request_id'       => ['required', 'uuid'],
        ])->validate();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{subject_type: string, subject_id: int, parent_type: ?string, parent_id: ?int}
     */
    public function subject(array $payload): array
    {
        return [
            'subject_type' => 'StockDeliveryItem',
            'subject_id'   => (int) $payload['stock_delivery_item_id'],
            'parent_type'  => 'StockDelivery',
            'parent_id'    => (int) $payload['stock_delivery_id'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{t: string, id: int}>
     */
    public function touchedRows(array $payload): array
    {
        $rows = [['t' => 'StockDeliveryItem', 'id' => (int) $payload['stock_delivery_item_id']]];

        if (!empty($payload['location_org_stock_id'])) {
            $rows[] = ['t' => 'LocationOrgStock', 'id' => (int) $payload['location_org_stock_id']];

            return $rows;
        }

        $rowId = LocationOrgStock::where('location_id', (int) $payload['location_id'])
            ->where('org_stock_id', StockDeliveryItem::whereKey((int) $payload['stock_delivery_item_id'])->select('org_stock_id'))
            ->value('id');

        $rows[] = $rowId ? ['t' => 'LocationOrgStock', 'id' => (int) $rowId] : ['t' => 'Location', 'id' => (int) $payload['location_id']];

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \Throwable
     */
    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        $requested = (float) $payload['quantity'];

        if ($alreadyDone = $this->alreadyDoneOnline($context, $payload, $requested)) {
            return $alreadyDone;
        }

        $user      = $context->user();
        $warehouse = $context->warehouse();

        $stockDelivery = StockDelivery::find((int) $payload['stock_delivery_id']);
        $item          = StockDeliveryItem::find((int) $payload['stock_delivery_item_id']);
        if (!$stockDelivery || !$item || $item->stock_delivery_id !== $stockDelivery->id || $stockDelivery->organisation_id !== $warehouse->organisation_id) {
            return MobileOfflineOutcome::refused(self::REASON_NOT_FOUND, MobileOfflineOutcome::INSTRUCTION_NONE, __('This delivery item is not in this warehouse any more.'), $requested);
        }

        [$location, $row, $locationReason] = $this->placingLocation($payload, $item, $warehouse->id);
        $code = $location?->code;

        if (!self::canViewGoodsIn($user, $warehouse) || !self::canBookInStockDeliveries($user, $warehouse)) {
            return $this->refusedOnShelf($context, $row, $location, self::REASON_NO_RIGHT, __('You can not book in stock deliveries.'), $requested, $item);
        }

        if ($locationReason) {
            return $locationReason === self::REASON_NOT_FOUND
                ? MobileOfflineOutcome::refused($locationReason, MobileOfflineOutcome::INSTRUCTION_NONE, $this->locationRefusalMessage($locationReason), $requested)
                : $this->refusedOnShelf($context, $row, $location, $locationReason, $this->locationRefusalMessage($locationReason), $requested, $item);
        }

        $item = StockDeliveryItem::lockForUpdate()->findOrFail($item->id);
        if ($row) {
            $row = $this->lockLocationOrgStocks([$row->id])->get($row->id);
        }

        if ($item->state === StockDeliveryItemStateEnum::CANCELLED) {
            return $this->refusedOnShelf($context, $row, $location, self::REASON_WRONG_STATE, __('This item was cancelled.'), $requested, $item);
        }

        if (!$item->stockDelivery->isInGoodsIn()) {
            return $this->refusedOnShelf($context, $row, $location, 'delivery_closed', __('This delivery is no longer being booked in, so the put-away was not recorded.'), $requested, $item);
        }

        $remaining = max(0, ((float) $item->unit_quantity_checked - (float) $item->unit_quantity_placed) / $item->unitsPerSko());
        if ($remaining <= self::TOLERANCE) {
            return $this->refusedOnShelf($context, $row, $location, 'over_checked', __('Nothing was left to place on this item, so the put-away was not recorded.'), $requested, $item);
        }

        $applied      = min($requested, $remaining);
        $countedSince = $row !== null && $context->countedSince($row);
        $placedBefore = (float) $item->unit_quantity_placed;
        $lastSowingId = (int) Sowing::max('id');

        $modelData = ['quantity' => $applied, ...($row ? ['location_org_stock_id' => $row->id] : ['location_id' => $location->id])];
        if (!empty($payload['set_as_picking_location']) && !$this->hasPickingLocation($this->skoLocationsInWarehouse($warehouse, [$item->org_stock_id])->get($item->org_stock_id, collect()))) {
            $modelData['set_as_picking_location'] = true;
        }

        $lastMovementId = $this->lastMovementId();
        $result         = $this->inSavepoint(fn () => UpsertStockDeliveryItemPlaced::make()->action($item, $modelData, $user, $countedSince), $requested);
        if ($result instanceof MobileOfflineOutcome) {
            return $result;
        }

        if (!$row) {
            $row = LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $item->org_stock_id)->firstOrFail();
            $context->recordCreated($row);
        }
        Sowing::where('stock_delivery_item_id', $item->id)->where('id', '>', $lastSowingId)->orderBy('id')->get()->each(fn (Sowing $sowing) => $context->recordCreated($sowing));
        $context->recordUpdated($result, ['unit_quantity_placed' => $placedBefore], ['unit_quantity_placed' => (float) $result->unit_quantity_placed]);
        $this->recordMovementsSince($context, $item->org_stock_id, [$location->id], $lastMovementId);
        $this->rememberClientRequest($context, $payload);

        $message = __('Placed :quantity in :location.', ['quantity' => $this->quantityLabel($applied), 'location' => $code]);

        if ($requested - $applied > self::TOLERANCE) {
            $extra = $requested - $applied;
            if (!$countedSince) {
                $context->requestStockCheck($row, $this->stockCheckDescription($item, $extra, $code));
            }

            return MobileOfflineOutcome::partlyApplied($requested, $applied, 'over_checked', MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF, $message.' '.__('Only :applied was left to place, so :extra more in :location are not recorded.', [
                'applied'  => $this->quantityLabel($applied),
                'extra'    => $this->quantityLabel($extra),
                'location' => $code,
            ]), $countedSince);
        }

        return MobileOfflineOutcome::applied($requested, $applied, $countedSince, $message);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: Location|null, 1: LocationOrgStock|null, 2: string|null}
     */
    private function placingLocation(array $payload, StockDeliveryItem $item, int $warehouseId): array
    {
        if (!empty($payload['location_org_stock_id'])) {
            $row = LocationOrgStock::with('location')->find((int) $payload['location_org_stock_id']);
            if (!$row || $row->org_stock_id !== $item->org_stock_id || $row->warehouse_id !== $warehouseId) {
                return [null, null, self::REASON_NOT_FOUND];
            }

            return [$row->location, $row, $this->locationRefusalReason($row->location, $warehouseId)];
        }

        $location = Location::withTrashed()->find((int) $payload['location_id']);
        $row      = $location ? LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $item->org_stock_id)->first() : null;

        return [$location, $row, $this->locationRefusalReason($location, $warehouseId)];
    }

    private function refusedOnShelf(MobileOfflineContext $context, ?LocationOrgStock $row, ?Location $location, string $reason, string $message, float $requested, StockDeliveryItem $item): MobileOfflineOutcome
    {
        if ($location && $location->warehouse_id === $context->warehouse()->id && !($row && $context->countedSince($row))) {
            $context->requestStockCheck($row ?? $location, $this->stockCheckDescription($item, $requested, $location->code));
        }

        return MobileOfflineOutcome::refused($reason, MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF, $message, $requested);
    }

    private function stockCheckDescription(StockDeliveryItem $item, float $quantity, ?string $locationCode): string
    {
        return __('Offline put-away of :quantity of :sko from :delivery in :location was not recorded. Count the location.', [
            'quantity' => $this->quantityLabel($quantity),
            'sko'      => $item->orgStock?->code,
            'delivery' => $item->stockDelivery?->reference,
            'location' => $locationCode,
        ]);
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return $this->rollbackRefusal($operation) === null;
    }

    /**
     * @throws ValidationException
     * @throws \Throwable
     */
    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        DB::transaction(function () use ($operation, $user) {
            if ($refusal = $this->rollbackRefusal($operation)) {
                $this->refusedRollback($refusal);
            }

            foreach ($this->createdSowings($operation) as $sowing) {
                DeleteSowing::make()->action(Sowing::lockForUpdate()->findOrFail($sowing->id), $user);
            }
        });
    }

    private function rollbackRefusal(MobileOfflineOperation $operation): ?string
    {
        $sowings = $this->createdSowings($operation);

        if ($sowings->isEmpty()) {
            return __('This put-away was already undone.');
        }

        $stockDelivery = StockDelivery::find((int) ($operation->payload['stock_delivery_id'] ?? 0));
        if (!$stockDelivery || !$stockDelivery->acceptsPutAwayUndo()) {
            return __('A put-away can only be undone while the delivery is being booked in');
        }

        return null;
    }

    /**
     * @return Collection<int, Sowing>
     */
    private function createdSowings(MobileOfflineOperation $operation): Collection
    {
        return Sowing::whereIn('id', $this->createdEffectIds($operation, new Sowing()))->orderBy('id')->get();
    }
}

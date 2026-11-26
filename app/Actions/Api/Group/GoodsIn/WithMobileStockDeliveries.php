<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\AddItemNotOnPurchaseOrderToStockDelivery;
use App\Actions\GoodsIn\StockDelivery\CancelStockDelivery;
use App\Actions\GoodsIn\StockDelivery\ReceiveStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryGoodsInChecklist;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait WithMobileStockDeliveries
{
    use WithMobileGoodsIn;

    public const array STOCK_DELIVERY_SOURCES = [
        'suppliers' => ['OrgSupplier', 'Production'],
        'agents'    => ['OrgAgent'],
        'partners'  => ['OrgPartner'],
    ];

    public const array ACTIVE_STOCK_DELIVERY_STATES = [
        StockDeliveryStateEnum::DISPATCHED,
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
    ];

    protected function ensureCanViewStockDeliveries(User $user, Warehouse $warehouse): void
    {
        $this->ensureWarehouseAccess($user, $warehouse, self::canViewGoodsIn($user, $warehouse), __('You can not see the stock deliveries of this warehouse.'));
    }

    protected function ensureCanViewStockDelivery(User $user, Warehouse $warehouse, StockDelivery $stockDelivery): void
    {
        $this->ensureCanViewStockDeliveries($user, $warehouse);
        abort_unless($stockDelivery->organisation_id === $warehouse->organisation_id, 404);
    }

    protected function ensureCanBookIn(User $user, Warehouse $warehouse, StockDelivery $stockDelivery): void
    {
        $this->ensureCanViewStockDelivery($user, $warehouse, $stockDelivery);
        abort_unless(self::canBookInStockDeliveries($user, $warehouse), 403, __('You can not book in stock deliveries.'));
    }

    protected function ensureItemOfStockDelivery(StockDelivery $stockDelivery, StockDeliveryItem $stockDeliveryItem): void
    {
        abort_unless($stockDeliveryItem->stock_delivery_id === $stockDelivery->id, 404);
    }

    protected function ensureStockDeliveryInGoodsIn(StockDelivery $stockDelivery): void
    {
        if (!$stockDelivery->isInGoodsIn()) {
            $this->refuseState(__('This can not be done while the delivery is :state.', ['state' => StockDeliveryStateEnum::labels()[$stockDelivery->state->value]]));
        }
    }

    protected function ensureItemNotCancelled(StockDeliveryItem $stockDeliveryItem): void
    {
        if ($stockDeliveryItem->state === StockDeliveryItemStateEnum::CANCELLED) {
            $this->refuseState(__('This item was cancelled.'));
        }
    }

    protected function canBeReceived(StockDelivery $stockDelivery): bool
    {
        return in_array($stockDelivery->state, ReceiveStockDelivery::RECEIVABLE_STATES, true)
            && !$this->isWaitingForPartner($stockDelivery);
    }

    protected function isWaitingForPartner(StockDelivery $stockDelivery): bool
    {
        return $stockDelivery->isManagedByPartner() && $stockDelivery->state !== StockDeliveryStateEnum::DISPATCHED;
    }

    protected function canBeChecked(StockDelivery $stockDelivery): bool
    {
        return $stockDelivery->isInGoodsIn() || $stockDelivery->state === StockDeliveryStateEnum::BOOKED_IN;
    }

    public static function stockDeliverySource(?string $parentType): ?string
    {
        foreach (self::STOCK_DELIVERY_SOURCES as $source => $parentTypes) {
            if (in_array($parentType, $parentTypes, true)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @return array{type: string|null, code: string|null, name: string|null}
     */
    protected function stockDeliverySupplier(?string $parentType, ?string $parentCode, ?string $parentName): array
    {
        return [
            'type' => $parentType,
            'code' => $parentCode,
            'name' => $parentName,
        ];
    }

    protected function estimatedReceivingDate(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function stockDeliveryActions(Warehouse $warehouse, StockDelivery $stockDelivery, User $user): array
    {
        $canBookIn = self::canBookInStockDeliveries($user, $warehouse);
        $actions   = [];

        if ($canBookIn && $this->canBeReceived($stockDelivery)) {
            $actions[] = $this->actionOption('receive', __('Mark as received'));
        }

        if ($stockDelivery->state === StockDeliveryStateEnum::RECEIVED && self::canSuperviseStockDeliveries($user, $warehouse)) {
            $actions[] = $this->actionOption('unreceive', __('Unmark as received'));
        }

        if ($canBookIn && $this->canBeChecked($stockDelivery)) {
            $actions[] = $this->actionOption('check', __('Check'));
        }

        if ($canBookIn && $stockDelivery->isInGoodsIn()) {
            $actions[] = $this->actionOption('place', __('Place'));
        }

        if ($canBookIn && in_array($stockDelivery->state, GetStockDeliveryGoodsInChecklist::STATES, true)) {
            $actions[] = $this->actionOption('checklist', __('Checklist'));
        }

        if ($canBookIn && AddItemNotOnPurchaseOrderToStockDelivery::acceptsItems($stockDelivery)) {
            $actions[] = $this->actionOption('add_item', __('Add extra item'));
        }

        if (self::canCancelStockDeliveries($user, $stockDelivery) && $this->canBeCancelledFromTheApp($stockDelivery)) {
            $actions[] = $this->actionOption('cancel', __('Cancel stock delivery'));
        }

        return $actions;
    }

    public static function canCancelStockDeliveries(User $user, StockDelivery $stockDelivery): bool
    {
        return $user->authTo("procurement.$stockDelivery->organisation_id.edit");
    }

    protected function canBeCancelledFromTheApp(StockDelivery $stockDelivery): bool
    {
        return !$stockDelivery->isManagedByPartner()
            && CancelStockDelivery::canBeCancelled($stockDelivery)
            && !$stockDelivery->items()->where('unit_quantity_placed', '>', 0)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function stockDeliveryDetail(Warehouse $warehouse, StockDelivery $stockDelivery, User $user): array
    {
        $items       = $this->stockDeliveryItems($stockDelivery);
        $itemsStates = $items->countBy(fn (StockDeliveryItem $item) => $item->state->value);
        $countOf     = fn (StockDeliveryItemStateEnum ...$states) => collect($states)->sum(fn (StockDeliveryItemStateEnum $state) => (int) $itemsStates->get($state->value, 0));
        $showPage    = ShowStockDelivery::make();

        return [
            'id'                       => $stockDelivery->id,
            'reference'                => $stockDelivery->reference,
            'agent_reference'          => $stockDelivery->agent_reference,
            'state'                    => $stockDelivery->state->value,
            'state_label'              => StockDeliveryStateEnum::labels()[$stockDelivery->state->value],
            'supplier'                 => $this->stockDeliverySupplier($stockDelivery->parent_type, $stockDelivery->parent_code, $stockDelivery->parent_name),
            'source'                   => self::stockDeliverySource($stockDelivery->parent_type),
            'dispatched_at'            => $this->isoDateTime($stockDelivery->dispatched_at),
            'received_at'              => $this->isoDateTime($stockDelivery->received_at),
            'estimated_receiving_date' => $this->estimatedReceivingDate(Arr::get($stockDelivery->data, 'estimated_receiving_date')),
            'delivery'                 => Arr::except($showPage->getDeliveryDetails($stockDelivery), ['delivery_address']),
            'purchase_orders'          => $stockDelivery->purchaseOrders()
                ->orderBy('purchase_orders.id')
                ->get(['purchase_orders.id', 'purchase_orders.reference'])
                ->map(fn ($purchaseOrder) => ['id' => $purchaseOrder->id, 'reference' => $purchaseOrder->reference])
                ->all(),
            'summary'                  => [
                'number_items'          => $items->count() - $countOf(StockDeliveryItemStateEnum::CANCELLED),
                'number_checked'        => $countOf(StockDeliveryItemStateEnum::CHECKED, StockDeliveryItemStateEnum::PLACED, StockDeliveryItemStateEnum::NOT_RECEIVED),
                'number_placed'         => $countOf(StockDeliveryItemStateEnum::PLACED),
                'number_not_received'   => $countOf(StockDeliveryItemStateEnum::NOT_RECEIVED),
                'number_new_org_stocks' => $showPage->numberOfNewOrgStocks($stockDelivery),
                'under_delivered'       => (int) $stockDelivery->number_stock_delivery_items_under_delivered,
                'over_delivered'        => (int) $stockDelivery->number_stock_delivery_items_over_delivered,
            ],
            'items'                    => $this->stockDeliveryItemRows($warehouse, $items),
            'checklist'                => $this->stockDeliveryChecklist($stockDelivery),
            'available_actions'        => $this->stockDeliveryActions($warehouse, $stockDelivery, $user),
        ];
    }

    /**
     * @return Collection<int, StockDeliveryItem>
     */
    protected function stockDeliveryItems(StockDelivery $stockDelivery): Collection
    {
        $doneStates = [StockDeliveryItemStateEnum::PLACED->value, StockDeliveryItemStateEnum::CANCELLED->value];

        return StockDeliveryItem::query()
            ->where('stock_delivery_items.stock_delivery_id', $stockDelivery->id)
            ->leftJoin('org_stocks', 'org_stocks.id', '=', 'stock_delivery_items.org_stock_id')
            ->leftJoin('purchase_order_transactions as line_order_transaction', 'line_order_transaction.id', '=', DB::raw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint"))
            ->leftJoin('purchase_orders as line_purchase_order', 'line_purchase_order.id', '=', 'line_order_transaction.purchase_order_id')
            ->select(['stock_delivery_items.*', 'line_purchase_order.reference as purchase_order_reference'])
            ->with([
                'orgStock.tradeUnits.image',
                'supplierProduct',
                'sowings' => fn ($sowings) => $sowings->where('type', SowingTypeEnum::SOW)->orderBy('id')->with('location'),
            ])
            ->orderByRaw('case when stock_delivery_items.state in (?, ?) then 1 else 0 end', $doneStates)
            ->orderBy('org_stocks.code')
            ->orderBy('stock_delivery_items.id')
            ->get();
    }

    /**
     * @param Collection<int, StockDeliveryItem> $items
     * @return array<int, array<string, mixed>>
     */
    protected function stockDeliveryItemRows(Warehouse $warehouse, Collection $items): array
    {
        $locationsBySko = $this->skoLocationsInWarehouse($warehouse, $items->pluck('org_stock_id')->filter()->unique()->values()->all());

        return $items->map(fn (StockDeliveryItem $item) => $this->stockDeliveryItemRow($item, $locationsBySko->get($item->org_stock_id, collect())))->values()->all();
    }

    /**
     * @param array<int, int> $orgStockIds
     * @return Collection<int, Collection<int, object>>
     */
    protected function skoLocationsInWarehouse(Warehouse $warehouse, array $orgStockIds): Collection
    {
        return StockDeliveryItemResource::locationsQuery()
            ->addSelect(['location_org_stocks.type', 'location_org_stocks.version'])
            ->whereIn('location_org_stocks.org_stock_id', $orgStockIds)
            ->where('location_org_stocks.warehouse_id', $warehouse->id)
            ->where('locations.warehouse_id', $warehouse->id)
            ->whereNull('locations.deleted_at')
            ->get()
            ->groupBy('org_stock_id');
    }

    /**
     * @param Collection<int, object> $locations
     */
    protected function hasPickingLocation(Collection $locations): bool
    {
        return $locations->contains(fn ($location) => $location->default_wholesale_picking_location || $location->default_dropshipping_picking_location);
    }

    protected function isAwaitingCheck(StockDeliveryItem $item): bool
    {
        return in_array($item->state, [
            StockDeliveryItemStateEnum::IN_PROCESS,
            StockDeliveryItemStateEnum::CONFIRMED,
            StockDeliveryItemStateEnum::READY_TO_SHIP,
            StockDeliveryItemStateEnum::DISPATCHED,
            StockDeliveryItemStateEnum::RECEIVED,
        ], true);
    }

    /**
     * @param Collection<int, object> $locations
     * @return array<string, mixed>
     */
    protected function stockDeliveryItemRow(StockDeliveryItem $item, Collection $locations): array
    {
        $orgStock        = $item->orgStock;
        $supplierProduct = $item->supplierProduct;
        $packedIn        = $orgStock?->packed_in;
        $unitsPerSko     = $item->unitsPerSko();
        $checked         = (float) $item->unit_quantity_checked;
        $placed          = (float) $item->unit_quantity_placed;

        return [
            'id'                       => $item->id,
            'state'                    => $item->state->value,
            'state_label'              => StockDeliveryItemStateEnum::labels()[$item->state->value],
            'supplier_product'         => $supplierProduct ? ['code' => $supplierProduct->code, 'name' => $supplierProduct->name] : null,
            'purchase_order_reference' => $item->getAttribute('purchase_order_reference'),
            'is_new_org_stock'         => $orgStock?->has_been_in_warehouse === false,
            'sko'                      => [
                'id'        => $orgStock?->id,
                'code'      => $orgStock?->code,
                'name'      => $orgStock?->name,
                'packed_in' => $packedIn === null ? null : (int) $packedIn,
                'barcode'   => $orgStock?->barcode,
                'image_url' => $orgStock ? $this->orgStockImageUrl($orgStock) : null,
            ],
            ...$this->skoFields('sko_expected', (float) $item->unit_quantity / $unitsPerSko, $packedIn),
            ...($this->isAwaitingCheck($item)
                ? ['sko_checked' => null, 'sko_checked_label' => null]
                : $this->skoFields('sko_checked', $checked / $unitsPerSko, $packedIn)),
            ...$this->skoFields('sko_placed', $placed / $unitsPerSko, $packedIn),
            ...$this->skoFields('sko_to_place', max(0, $checked - $placed) / $unitsPerSko, $packedIn),
            'has_picking_location'     => $this->hasPickingLocation($locations),
            'locations'                => $locations->map(fn ($location) => [
                'location_org_stock_id'   => $location->id,
                'location'                => ['id' => $location->location_id, 'code' => $location->location_code],
                'type'                    => $location->type,
                ...$this->skoFields('quantity', $location->quantity, $packedIn),
                'is_default_wholesale'    => (bool) $location->default_wholesale_picking_location,
                'is_default_dropshipping' => (bool) $location->default_dropshipping_picking_location,
                'version'                 => (int) $location->version,
            ])->values()->all(),
            'placements'               => $item->sowings->map(fn (Sowing $sowing) => [
                'id'       => $sowing->id,
                ...$this->skoFields('quantity', $sowing->quantity, $packedIn),
                'location' => $sowing->location ? ['id' => $sowing->location->id, 'code' => $sowing->location->code] : null,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function stockDeliveryChecklist(StockDelivery $stockDelivery): ?array
    {
        if (!in_array($stockDelivery->state, GetStockDeliveryGoodsInChecklist::STATES, true)) {
            return null;
        }

        $checklist = GetStockDeliveryGoodsInChecklist::make()->forPage($stockDelivery);

        if ($checklist['sections'] === []) {
            return null;
        }

        $results = Arr::get($stockDelivery->data, 'goods_in_checks', []);

        return [
            'sections'       => array_map(fn (array $section) => [
                'key'      => $section['key'],
                'title'    => $section['title'],
                'subtitle' => $section['subtitle'],
                'checks'   => array_map(fn (array $check) => [
                    'key'        => $check['key'],
                    'label'      => $check['label'],
                    'detail'     => $check['detail'],
                    'severity'   => $check['severity'],
                    'result'     => $check['result'],
                    'checked_by' => $check['checked_by'] ? ['id' => Arr::get($results, $check['key'].'.user_id'), 'name' => $check['checked_by']] : null,
                    'checked_at' => $this->isoDateTime($check['checked_at']),
                ], $section['checks']),
            ], $checklist['sections']),
            'total'          => $checklist['total'],
            'checked'        => $checklist['checked'],
            'failed'         => $checklist['failed'],
            'incident_types' => ProcurementNoteIncidentTypeEnum::valuesWithLabels(),
        ];
    }

    protected function respondWithStockDelivery(Warehouse $warehouse, StockDelivery $stockDelivery, User $user, string $status, string $message): JsonResponse
    {
        return response()->json([
            'data'   => $this->stockDeliveryDetail($warehouse, StockDelivery::findOrFail($stockDelivery->id), $user),
            'result' => [
                'status'  => $status,
                'message' => $message,
            ],
        ]);
    }
}

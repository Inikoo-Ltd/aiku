<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\Traits\BroadcastsMobileRecordChanges;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemCancelStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Transaction;
use App\Models\Catalogue\Shop;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Dispatching\DeliveryNoteItem
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $delivery_note_id
 * @property int|null $stock_family_id
 * @property int|null $stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $org_stock_id
 * @property int|null $transaction_id
 * @property int|null $invoice_transaction_id
 * @property string|null $notes
 * @property DeliveryNoteItemStateEnum $state
 * @property numeric|null $weight
 * @property numeric $quantity_required
 * @property numeric|null $quantity_picked
 * @property numeric|null $quantity_packed
 * @property numeric|null $quantity_dispatched
 * @property numeric $revenue_amount
 * @property numeric $org_revenue_amount
 * @property numeric $grp_revenue_amount
 * @property numeric|null $profit_amount
 * @property numeric|null $org_profit_amount
 * @property numeric|null $grp_profit_amount
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property DeliveryNoteItemSalesTypeEnum|null $sales_type
 * @property \Illuminate\Support\Carbon|null $date
 * @property string|null $queued_at
 * @property string|null $handling_at
 * @property string|null $handling_blocked_at
 * @property \Illuminate\Support\Carbon|null $packed_at
 * @property string|null $finalised_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $start_picking
 * @property string|null $end_picking
 * @property string|null $start_packing
 * @property string|null $end_packing
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property int|null $invoice_id
 * @property int $estimated_required_weight grams
 * @property int $estimated_picked_weight grams
 * @property numeric|null $quantity_not_picked
 * @property bool $is_handled
 * @property bool|null $need_packing
 * @property bool|null $is_packed
 * @property bool $is_done
 * @property DeliveryNoteItemCancelStateEnum|null $cancel_state
 * @property int|null $picking_session_id
 * @property numeric|null $original_quantity_required
 * @property string|null $batch_code
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string|null $locked_at
 * @property numeric $quantity_waiting_warehouse
 * @property numeric $quantity_waiting_crm
 * @property bool $has_waiting_warehouse
 * @property bool $has_waiting_crm
 * @property \Illuminate\Support\Carbon|null $waiting_warehouse_released_at
 * @property \Illuminate\Support\Carbon|null $waiting_production_at
 * @property int|null $batch_code_id
 * @property numeric $quantity_returned
 * @property \Illuminate\Support\Carbon|null $composition_dirty_at The SKU composition changed after picking work was done, a human must roll back or confirm
 * @property numeric|null $composition_dirty_quantity_required What quantity_required would be under the new composition
 * @property bool $is_dirty
 * @property array<array-key, mixed>|null $boxes [{box, quantity}] parcel numbers the picked quantity was packed in, for box packing lists
 * @property-read \App\Models\Dispatching\BatchCode|null $batchCode
 * @property-read \App\Models\Dispatching\DeliveryNote|null $deliveryNote
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read OrgStock|null $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dispatching\Packing> $packings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dispatching\Picking> $pickings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReturnDeliveryNoteItem> $returnDeliveryNoteItems
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sowing> $sowings
 * @property-read Transaction|null $transaction
 * @method static Builder<static>|DeliveryNoteItem newModelQuery()
 * @method static Builder<static>|DeliveryNoteItem newQuery()
 * @method static Builder<static>|DeliveryNoteItem query()
 * @mixin Eloquent
 */
class DeliveryNoteItem extends Model
{
    use BroadcastsMobileRecordChanges;
    use InShop;

    protected $table = 'delivery_note_items';

    protected $casts = [
        'data'         => 'array',
        'boxes'        => 'array',
        'state'        => DeliveryNoteItemStateEnum::class,
        'sales_type'   => DeliveryNoteItemSalesTypeEnum::class,
        'cancel_state' => DeliveryNoteItemCancelStateEnum::class,
        'replacement_reason' => DeliveryNoteItemReplacementReasonEnum::class,

        'date'                       => 'datetime',
        'composition_dirty_at'       => 'datetime',
        'order_submitted_at'         => 'datetime',
        'assigned_at'                => 'datetime',
        'picking_at'                 => 'datetime',
        'picked_at'                  => 'datetime',
        'packing_at'                 => 'datetime',
        'packed_at'                  => 'datetime',
        'dispatched_at'              => 'datetime',
        'cancelled_at'               => 'datetime',
        'waiting_warehouse_released_at' => 'datetime',
        'waiting_production_at'      => 'datetime',
        'fetched_at'                 => 'datetime',
        'last_fetched_at'            => 'datetime',
        'expiry_date'                => 'date',
        'is_handled'                 => 'boolean',
        'is_packed'                  => 'boolean',
        'is_done'                    => 'boolean',
        'need_packing'               => 'boolean',
        'quantity_waiting_warehouse' => 'decimal:6',
        'quantity_waiting_crm'       => 'decimal:6',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    /**
     * Both flags are generated columns in the database, so the row can never disagree with its
     * quantity. A model in hand that has just been updated has not read the row back, so the same
     * derivation is applied here: the flag is the quantity, in memory as on disk.
     */
    public function getHasWaitingWarehouseAttribute(): bool
    {
        return (float)$this->quantity_waiting_warehouse > 0;
    }

    public function getHasWaitingCrmAttribute(): bool
    {
        return (float)$this->quantity_waiting_crm > 0;
    }

    protected static function booted(): void
    {
        static::creating(
            function (DeliveryNoteItem $deliveryNoteItem) {
                if ($deliveryNoteItem->quantity_required) {
                    $deliveryNoteItem->original_quantity_required = $deliveryNoteItem->quantity_required;
                }
            }
        );

        static::saved(
            function (DeliveryNoteItem $deliveryNoteItem) {
                if ($deliveryNoteItem->wasRecentlyCreated || $deliveryNoteItem->wasChanged(['state', 'org_stock_id', 'quantity_required', 'quantity_picked', 'quantity_not_picked'])) {
                    OrgStockHydrateQuantityInLocations::dispatch($deliveryNoteItem->org_stock_id)->delay(2);
                    if ($deliveryNoteItem->wasChanged('org_stock_id')) {
                        OrgStockHydrateQuantityInLocations::dispatch($deliveryNoteItem->getOriginal('org_stock_id'))->delay(2);
                    }
                }
            }
        );

        static::deleted(
            fn (DeliveryNoteItem $deliveryNoteItem) => OrgStockHydrateQuantityInLocations::dispatch($deliveryNoteItem->org_stock_id)->delay(2)
        );
    }

    public function pickings(): HasMany
    {
        return $this->hasMany(Picking::class);
    }

    public function packings(): HasMany
    {
        return $this->hasMany(Packing::class);
    }

    public function sowings(): HasMany
    {
        return $this->hasMany(Sowing::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /**
     * INI-484. A line copies its note's company, so a line worked in another company's warehouse is told apart from its own
     * columns and its shop, without loading the note. A line loaded without them asks its note.
     */
    public function isCrossDispatch(): bool
    {
        if (!array_key_exists('organisation_id', $this->attributes) || !array_key_exists('shop_id', $this->attributes)) {
            return (bool) DeliveryNote::withTrashed()->find($this->delivery_note_id ?? $this->newQueryWithoutScopes()->whereKey($this->getKey())->value('delivery_note_id'))?->isCrossDispatch();
        }

        $shopOrganisationId = $this->relationLoaded('shop') && $this->shop && !array_key_exists('organisation_id', $this->shop->getAttributes())
            ? Shop::whereKey($this->shop_id)->value('organisation_id')
            : $this->shop?->organisation_id;

        return $this->organisation_id !== null && $shopOrganisationId !== null && (int) $this->organisation_id !== (int) $shopOrganisationId;
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function batchCode(): BelongsTo
    {
        return $this->belongsTo(BatchCode::class);
    }

    public function returnDeliveryNoteItems(): HasMany
    {
        return $this->hasMany(ReturnDeliveryNoteItem::class, 'delivery_note_items_id');
    }

    /**
     * The batches picked for this line, as recorded by the stock taken off the shelves, falling
     * back to the batch typed on a pick made before stock knew its batches.
     *
     * @return array<int, array{batch_code_id: int, quantity: float}>
     */
    public function pickedBatches(): array
    {
        $batches = [];
        foreach ($this->pickings()->orderBy('id')->get(['id', 'quantity', 'batch_code_id', 'org_stock_movement_id']) as $picking) {
            $moved = $picking->org_stock_movement_id
                ? DB::table('org_stock_movement_batches')->where('org_stock_movement_id', $picking->org_stock_movement_id)->where('quantity', '<', 0)->orderBy('id')->get(['batch_code_id', 'quantity'])
                : collect();

            if ($moved->isEmpty() && $picking->batch_code_id) {
                $batches[] = ['batch_code_id' => $picking->batch_code_id, 'quantity' => (float) $picking->quantity];
            }
            foreach ($moved as $row) {
                $batches[] = ['batch_code_id' => $row->batch_code_id, 'quantity' => -(float) $row->quantity];
            }
        }

        return $batches;
    }

    /**
     * SKOs a line needs for an order quantity of the product. A partner order that names a whole
     * number of SKOs stores the product quantity as a rounded fraction (100 SKOs of a pack of 3 is
     * 33.333333), so a product of that comes back a hair off the whole number and is snapped to it.
     */
    public static function requiredQuantity(float $skosPerProduct, float $productQuantity): float
    {
        $quantity = $skosPerProduct * $productQuantity;
        $whole    = round($quantity);

        return $whole >= 1 && abs($quantity - $whole) < 0.00001 ? $whole : $quantity;
    }

    /**
     * HELP-4985. A waiting item whose missing quantity is now on a shelf of its note's warehouse, so the picker can go back for it.
     * Not counted: stock in the production factory location or a partner's goods-out location, and lines production still has to make or fill.
     * ponytail: ignores stock other notes are about to pick from the same shelf; reserve it if two notes race for the last units.
     */
    public static function readyToPickSql(string $item = 'delivery_note_items', string $note = 'delivery_notes'): string
    {
        return "($item.has_waiting_warehouse
            AND NOT EXISTS (SELECT 1 FROM partner_shopping_list_items p WHERE p.transaction_id = $item.transaction_id AND p.state = 'open')
            AND $item.quantity_waiting_warehouse <= (SELECT COALESCE(SUM(lo.quantity), 0) FROM location_org_stocks lo
                WHERE lo.org_stock_id = $item.org_stock_id AND lo.warehouse_id = $note.warehouse_id AND lo.quantity > 0
                AND NOT EXISTS (SELECT 1 FROM productions pr WHERE pr.organisation_id = $note.organisation_id AND pr.factory_location_id = lo.location_id)
                AND NOT EXISTS (SELECT 1 FROM org_partners op WHERE op.organisation_id = $note.organisation_id
                    AND lo.location_id IN (op.goods_out_location_id, op.gb_goods_out_location_id, op.cosmetic_goods_out_location_id))))";
    }

    /**
     * Waiting items (those waiting for production apart, HELP-4975), those waiting for production, and the notes whose every waiting item is ready to pick.
     *
     * @return array{count: int, ready_notes: int, production: int}
     */
    public static function waitingCounts($waitingItemsQuery): array
    {
        $counts = $waitingItemsQuery->toBase()
            ->selectRaw('count(*) FILTER (WHERE delivery_note_items.waiting_production_at IS NULL) as count')
            ->selectRaw('count(*) FILTER (WHERE delivery_note_items.waiting_production_at IS NOT NULL) as production')
            ->selectRaw('count(DISTINCT delivery_notes.id) FILTER (WHERE NOT EXISTS (SELECT 1 FROM delivery_note_items x WHERE x.delivery_note_id = delivery_notes.id AND x.has_waiting_warehouse AND (x.waiting_production_at IS NOT NULL OR NOT '.self::readyToPickSql('x').'))) as ready_notes')
            ->first();

        return ['count' => (int) $counts->count, 'ready_notes' => (int) $counts->ready_notes, 'production' => (int) $counts->production];
    }
}

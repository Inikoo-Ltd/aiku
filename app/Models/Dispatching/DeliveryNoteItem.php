<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Transaction;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property string|null $weight
 * @property string $quantity_required
 * @property string|null $quantity_picked
 * @property string|null $quantity_packed
 * @property string|null $quantity_dispatched
 * @property numeric $revenue_amount
 * @property numeric $org_revenue_amount
 * @property numeric $grp_revenue_amount
 * @property string|null $profit_amount
 * @property string|null $org_profit_amount
 * @property string|null $grp_profit_amount
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
 * @property string $quantity_not_picked
 * @property-read \App\Models\Dispatching\DeliveryNote $deliveryNote
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read OrgStock|null $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Dispatching\Packing|null $packings
 * @property-read \App\Models\Dispatching\Picking|null $pickings
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read Transaction|null $transaction
 * @method static Builder<static>|DeliveryNoteItem newModelQuery()
 * @method static Builder<static>|DeliveryNoteItem newQuery()
 * @method static Builder<static>|DeliveryNoteItem query()
 * @mixin Eloquent
 */
class DeliveryNoteItem extends Model
{
    use InShop;

    protected $table = 'delivery_note_items';

    protected $casts = [
        'data'       => 'array',
        'state'      => DeliveryNoteItemStateEnum::class,
        'sales_type' => DeliveryNoteItemSalesTypeEnum::class,

        'date'               => 'datetime',
        'order_submitted_at' => 'datetime',
        'assigned_at'        => 'datetime',
        'picking_at'         => 'datetime',
        'picked_at'          => 'datetime',
        'packing_at'         => 'datetime',
        'packed_at'          => 'datetime',
        'dispatched_at'      => 'datetime',
        'cancelled_at'       => 'datetime',
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',

        'revenue_amount'     => 'decimal:2',
        'org_revenue_amount' => 'decimal:2',
        'grp_revenue_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function pickings(): HasOne
    {
        return $this->hasOne(Picking::class);
    }

    public function packings(): HasOne
    {
        return $this->hasOne(Packing::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

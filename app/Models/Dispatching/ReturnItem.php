<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 12:00:00 Makassar Time.
 * Description: ReturnItem model for individual items in a return
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\Return\ReturnItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Transaction;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Dispatching\ReturnItem
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $return_id
 * @property int|null $delivery_note_item_id
 * @property int|null $transaction_id
 * @property int|null $stock_family_id
 * @property int|null $stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $org_stock_id
 * @property string $state
 * @property string|null $return_reason
 * @property string|null $notes
 * @property string|null $quantity_ordered
 * @property string|null $quantity_dispatched
 * @property string $quantity_returned
 * @property string|null $quantity_received
 * @property string|null $quantity_restocked
 * @property string|null $quantity_rejected
 * @property string|null $weight
 * @property string $unit_price
 * @property string $total_amount
 * @property string $refund_amount
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $inspected_at
 * @property \Illuminate\Support\Carbon|null $restocked_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read DeliveryNoteItem|null $deliveryNoteItem
 * @property-read OrgStock|null $orgStock
 * @property-read OrderReturn $return
 * @property-read Transaction|null $transaction
 */
class ReturnItem extends Model
{
    use InShop;

    protected $table = 'return_items';

    protected $casts = [
        'data'            => 'array',
        'state'           => ReturnItemStateEnum::class,
        'received_at'     => 'datetime',
        'inspected_at'    => 'datetime',
        'restocked_at'    => 'datetime',
        'rejected_at'     => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'unit_price'      => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'refund_amount'   => 'decimal:2',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function return(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function deliveryNoteItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteItem::class);
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

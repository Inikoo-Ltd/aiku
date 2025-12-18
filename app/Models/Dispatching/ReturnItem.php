<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: ReturnItem model for individual items within a customer return
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\Return\ReturnItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Transaction;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $stock_family_id
 * @property int|null $stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $org_stock_id
 * @property int|null $transaction_id
 * @property int|null $invoice_transaction_id
 * @property string|null $notes
 * @property ReturnItemStateEnum $state
 * @property string|null $weight
 * @property string $quantity_expected
 * @property string|null $quantity_received
 * @property string|null $quantity_accepted
 * @property string|null $quantity_rejected
 * @property string|null $quantity_restocked
 * @property numeric $revenue_amount
 * @property numeric $org_revenue_amount
 * @property numeric $grp_revenue_amount
 * @property string|null $refund_amount
 * @property string|null $org_refund_amount
 * @property string|null $grp_refund_amount
 * @property \Illuminate\Support\Carbon|null $date
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $inspecting_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property \Illuminate\Support\Carbon|null $restocked_at
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property int|null $invoice_id
 * @property int $estimated_weight grams
 * @property string|null $rejection_reason
 * @property string|null $condition
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read OrgStock|null $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Dispatching\OrderReturn $return
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read Transaction|null $transaction
 * @method static Builder<static>|ReturnItem newModelQuery()
 * @method static Builder<static>|ReturnItem newQuery()
 * @method static Builder<static>|ReturnItem query()
 * @mixin Eloquent
 */
class ReturnItem extends Model
{
    use InShop;

    protected $table = 'return_items';

    protected $casts = [
        'data'            => 'array',
        'state'           => ReturnItemStateEnum::class,
        'date'            => 'datetime',
        'received_at'     => 'datetime',
        'inspecting_at'   => 'datetime',
        'accepted_at'     => 'datetime',
        'rejected_at'     => 'datetime',
        'restocked_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'revenue_amount'     => 'decimal:2',
        'org_revenue_amount' => 'decimal:2',
        'grp_revenue_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function return(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
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

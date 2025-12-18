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
 * @property ReturnItemStateEnum $state
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read OrgStock|null $orgStock
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Dispatching\OrderReturn|null $return
 * @property-read \App\Models\Catalogue\Shop|null $shop
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

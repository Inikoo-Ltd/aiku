<?php

/*
 * author Arya Permana - Kirin
 * created on 19-11-2024-16h-57m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Ordering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sales_channel_id
 * @property string|null $first_used_at
 * @property string|null $last_used_at
 * @property int $number_customers
 * @property int $number_orders
 * @property int $number_invoices
 * @property int $number_delivery_notes
 * @property string $amount
 * @property string $org_amount
 * @property string $grp_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ordering\SalesChannel $salesChannel
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelStats query()
 * @mixin \Eloquent
 */
class SalesChannelStats extends Model
{
    protected $table = 'sales_channel_stats';

    protected $guarded = [];

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }
}

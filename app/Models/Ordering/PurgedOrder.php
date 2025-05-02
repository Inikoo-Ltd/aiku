<?php

/*
 * author Arya Permana - Kirin
 * created on 01-11-2024-11h-30m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Ordering;

use App\Enums\Ordering\PurgedOrder\PurgedOrderStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $customer_id
 * @property int $purge_id
 * @property int|null $order_id
 * @property PurgedOrderStatusEnum $status
 * @property string|null $purged_at
 * @property string|null $order_created_at
 * @property string|null $order_last_updated_at
 * @property int|null $number_transactions
 * @property string|null $net_amount Net amount of the deleted order
 * @property string|null $org_net_amount
 * @property string|null $grp_net_amount
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\Ordering\Order|null $order
 * @property-read \App\Models\Ordering\Purge $purge
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurgedOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurgedOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurgedOrder query()
 * @mixin \Eloquent
 */
class PurgedOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status'       => PurgedOrderStatusEnum::class,
    ];

    public function purge(): BelongsTo
    {
        return $this->belongsTo(Purge::class, 'purge_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }



}

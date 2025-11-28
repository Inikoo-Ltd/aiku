<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 09:06:53 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $invoices
 * @property int $refunds
 * @property int $orders
 * @property int $registrations
 * @property string $baskets_created_grp_currency
 * @property string $sales_grp_currency
 * @property string $revenue_grp_currency
 * @property string $lost_revenue_grp_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSalesMetrics newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSalesMetrics newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSalesMetrics query()
 * @mixin \Eloquent
 */
class GroupSalesMetrics extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}

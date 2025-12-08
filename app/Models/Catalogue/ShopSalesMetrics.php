<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 13:14:41 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Catalogue;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $invoices
 * @property int $refunds
 * @property int $orders
 * @property int $registrations
 * @property string $baskets_created
 * @property string $baskets_created_grp_currency
 * @property string $baskets_created_org_currency
 * @property string $sales
 * @property string $sales_grp_currency
 * @property string $sales_org_currency
 * @property string $revenue
 * @property string $revenue_grp_currency
 * @property string $revenue_org_currency
 * @property string $lost_revenue
 * @property string $lost_revenue_grp_currency
 * @property string $lost_revenue_org_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopSalesMetrics newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopSalesMetrics newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopSalesMetrics query()
 *
 * @mixin \Eloquent
 */
class ShopSalesMetrics extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

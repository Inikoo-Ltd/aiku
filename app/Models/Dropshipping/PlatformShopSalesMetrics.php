<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Tue, 17 Dec 2025 11:04:22 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Dropshipping;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $platform_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $invoices
 * @property int $new_channels
 * @property int $new_customers
 * @property int $new_portfolios
 * @property int $new_customer_client
 * @property string $sales
 * @property string $sales_grp_currency
 * @property string $sales_org_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\Dropshipping\Platform $platform
 * @property-read Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformShopSalesMetrics newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformShopSalesMetrics newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformShopSalesMetrics query()
 * @mixin \Eloquent
 */
class PlatformShopSalesMetrics extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime'
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

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}

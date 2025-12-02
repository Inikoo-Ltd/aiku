<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 28 Nov 2025 16:25:15 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Dropshipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $platform_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $invoices
 * @property int $new_channels
 * @property int $new_customers
 * @property int $new_portfolios
 * @property int $new_customer_client
 * @property string $sales_grp_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Dropshipping\Platform $platform
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSalesMetrics newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSalesMetrics newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSalesMetrics query()
 * @mixin \Eloquent
 */
class PlatformSalesMetrics extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}

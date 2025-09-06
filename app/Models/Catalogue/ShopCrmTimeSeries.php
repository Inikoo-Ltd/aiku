<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 17:16:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopCrmTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopCrmTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopCrmTimeSeries query()
 * @mixin \Eloquent
 */
class ShopCrmTimeSeries extends Model
{
    protected $table = 'shop_crm_time_series';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

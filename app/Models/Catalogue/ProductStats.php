<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 23:15:49 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $product_id
 * @property int $number_product_variants
 * @property int $number_customers_who_favourited
 * @property int $number_customers_who_un_favourited
 * @property int $number_customers_who_reminded
 * @property int $number_customers_who_un_reminded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_parent_webpages
 * @property-read \App\Models\Catalogue\Product $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStats query()
 * @mixin \Eloquent
 */
class ProductStats extends Model
{
    protected $table = 'product_stats';

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

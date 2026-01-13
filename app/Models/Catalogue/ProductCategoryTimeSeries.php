<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 14:41:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_category_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $type
 * @property-read \App\Models\Catalogue\ProductCategory $productCategory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\ProductCategoryTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCategoryTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCategoryTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCategoryTimeSeries query()
 * @mixin \Eloquent
 */
class ProductCategoryTimeSeries extends Model
{
    protected $table = 'product_category_time_series';

    protected $guarded = [];

    protected $casts = [
        'data'      => 'array',
        'frequency' => TimeSeriesFrequencyEnum::class,

    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(ProductCategoryTimeSeriesRecord::class);
    }

}

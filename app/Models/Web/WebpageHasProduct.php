<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-11h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Web;

use App\Enums\Web\Webpage\WebpageHasProductTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $webpage_id
 * @property int $product_id
 * @property WebpageHasProductTypeEnum $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Product $product
 * @property-read \App\Models\Web\Webpage $webpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageHasProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageHasProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageHasProduct query()
 * @mixin \Eloquent
 */
class WebpageHasProduct extends Model
{
    protected $casts = [
        'type'  => WebpageHasProductTypeEnum::class,
    ];

    protected $attributes = [
    ];

    protected $guarded = [];

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

}

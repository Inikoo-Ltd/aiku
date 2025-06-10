<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-11h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Web;

use App\Enums\Web\Webpage\WebpageHasProductStateEnum;
use App\Enums\Web\Webpage\WebpageHasProductTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

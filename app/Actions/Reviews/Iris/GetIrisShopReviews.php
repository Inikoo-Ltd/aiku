<?php

/*
 * Author Louis Perez
 * Created on 06-07-2026-14h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Reviews\Iris;

use App\Actions\Reviews\Iris\Traits\WithGetIrisReviewsTrait;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisShopReviews
{
    use AsObject;
    use WithGetIrisReviewsTrait;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        return $this->getIrisReviews($shop, $prefix);
    }
}

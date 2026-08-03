<?php

/*
 * Author Louis Perez
 * Created on 06-07-2026-14h-24m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Reviews\Iris;

use App\Actions\Reviews\Iris\Traits\WithGetIrisReviewsTrait;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisProductCategoryReviews
{
    use AsObject;
    use WithGetIrisReviewsTrait;

    public function handle(ProductCategory $productCategory, $prefix = null): LengthAwarePaginator
    {
        return $this->getIrisReviews($productCategory, $prefix);
    }
}

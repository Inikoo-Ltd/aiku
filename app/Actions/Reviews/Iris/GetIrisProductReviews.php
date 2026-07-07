<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:52:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Iris;

use App\Actions\Reviews\Iris\Traits\WithGetIrisReviewsTrait;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisProductReviews
{
    use AsObject;
    use WithGetIrisReviewsTrait;

    public function handle(Product $product, $prefix = null): LengthAwarePaginator
    {
        return $this->getIrisReviews($product, $prefix);
    }
}

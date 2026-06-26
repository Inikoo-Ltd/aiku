<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-05-22 09:21
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Traits;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;

trait WithLuigiAvailabilityChecklist
{
    public function getLuigiAvailabilityChecklist(Product $product): array
    {
        $isActiveOrDiscontinuing = $product->state == ProductStateEnum::ACTIVE || $product->state == ProductStateEnum::DISCONTINUING;

        return [
            [
                'label'  => __('Product state is Active or Discontinuing'),
                'passed' => $isActiveOrDiscontinuing,
                'detail' => $isActiveOrDiscontinuing ? null : __('Current state: :state', ['state' => $product->state->value]),
            ],
            [
                'label'  => __('Webpage is live'),
                'passed' => (bool) $product->has_live_webpage,
                'detail' => $product->has_live_webpage ? null : __('Webpage does not have a live state'),
            ],
            [
                'label'  => __('Product is main variant'),
                'passed' => (bool) $product->is_main,
                'detail' => $product->is_main ? null : __('This product is not the main variant'),
            ],
            [
                'label'  => __('Product is for sale'),
                'passed' => (bool) $product->is_for_sale,
                'detail' => $product->is_for_sale ? null : __('Product is not marked as for sale'),
            ],
        ];
    }
}

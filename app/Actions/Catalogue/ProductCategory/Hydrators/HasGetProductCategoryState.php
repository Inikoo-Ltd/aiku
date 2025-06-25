<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Jun 2024 00:13:46 Central European Summer Time, Abu Dhabi Airport
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use Illuminate\Support\Arr;

trait HasGetProductCategoryState
{
    public function getProductCategoryState($stats): ProductCategoryStateEnum
    {

        if ($stats['number_products'] == 0) {
            return ProductCategoryStateEnum::IN_PROCESS;
        }

        if (Arr::get($stats, 'number_products_state_active', 0) > 0) {
            return ProductCategoryStateEnum::ACTIVE;
        }

        if (Arr::get($stats, 'number_products_state_discontinuing', 0) > 0) {
            return ProductCategoryStateEnum::DISCONTINUING;
        }

        if (Arr::get($stats, 'number_products_state_in_process', 0) > 0) {
            return ProductCategoryStateEnum::IN_PROCESS;
        }

        return ProductCategoryStateEnum::DISCONTINUED;

    }
}

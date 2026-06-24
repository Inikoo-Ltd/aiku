<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 23:53:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaOrderTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TRANSACTIONS                       = 'transactions';

    case ORDER_REVIEWS                      = 'order_reviews';

    case FAMILY_REVIEWS                     = 'family_reviews';

    case PRODUCT_REVIEWS                    = 'product_reviews';




    public function blueprint(): array
    {
        return match ($this) {

            RetinaOrderTabsEnum::TRANSACTIONS => [
                'title' => __('Transactions'),
                'icon'  => 'fal fa-bars',
            ],

            RetinaOrderTabsEnum::ORDER_REVIEWS => [
                'title' => __('Order review'),
                'icon'  => 'fal fa-star',
            ],

            RetinaOrderTabsEnum::FAMILY_REVIEWS => [
                'title' => __('Families review'),
                'icon'  => 'fal fa-star',
            ],

            RetinaOrderTabsEnum::PRODUCT_REVIEWS => [
                'title' => __('Products review'),
                'icon'  => 'fal fa-star',
            ],


        };
    }
}

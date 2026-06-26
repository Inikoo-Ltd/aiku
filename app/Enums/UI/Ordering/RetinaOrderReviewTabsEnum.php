<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 23:53:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaOrderReviewTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERALL_REVIEW                     = 'overall_review';
    case PRODUCT_REVIEWS                    = 'product_reviews';
    case FAMILY_REVIEWS                     = 'family_reviews';


    public function blueprint(): array
    {
        return match ($this) {
            RetinaOrderReviewTabsEnum::OVERALL_REVIEW => [
                'title' => __('Overall review'),
                'icon'  => 'fal fa-star',
            ],
            RetinaOrderReviewTabsEnum::FAMILY_REVIEWS => [
                'title' => __('Families review'),
                'icon'  => 'fal fa-folder',
            ],
            RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS => [
                'title' => __('Products review'),
                'icon'  => 'fal fa-cube',
            ],
        };
    }
}

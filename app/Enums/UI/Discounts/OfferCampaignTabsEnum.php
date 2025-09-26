<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:24:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Discounts;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OfferCampaignTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case OVERVIEW            = 'overview';
    case OFFERS              = 'offers';
    case HISTORY             = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            OfferCampaignTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-info-circle',
            ],
            OfferCampaignTabsEnum::OFFERS => [
                'title' => __('Offers'),
                'icon'  => 'fal fa-badge-percent',
            ],
            OfferCampaignTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon-only',
                'align' => 'right',
            ],
        };
    }
}

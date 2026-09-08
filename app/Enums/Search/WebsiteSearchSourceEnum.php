<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Search;

use App\Enums\EnumHelperTrait;

/**
 * Which storefront control the visitor used to start a search. The mobile overlay can be
 * opened from three different places, and only this tells them apart: they all end up
 * calling the very same search endpoint.
 */
enum WebsiteSearchSourceEnum: string
{
    use EnumHelperTrait;

    case MOBILE_TOP_BAR         = 'mobile_top_bar';
    case MOBILE_FLOATING_BUTTON = 'mobile_floating_button';
    case MOBILE_SIDEBAR         = 'mobile_sidebar';
    case DESKTOP_BAR            = 'desktop_bar';
    case SEARCH_PAGE            = 'search_page';

    public static function labels(): array
    {
        return [
            'mobile_top_bar'         => __('Mobile top bar'),
            'mobile_floating_button' => __('Mobile floating button'),
            'mobile_sidebar'         => __('Mobile sidebar'),
            'desktop_bar'            => __('Desktop search bar'),
            'search_page'            => __('Search page'),
        ];
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShoppingAssistantPerformanceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW      = 'overview';
    case CONVERSATIONS = 'conversations';
    case SHOPS         = 'shops';

    public function blueprint(): array
    {
        return match ($this) {
            ShoppingAssistantPerformanceTabsEnum::OVERVIEW => [
                'title'   => __('Overview'),
                'icon'    => 'fal fa-chart-line',
                'tooltip' => __('What the AI assistant did for sales in the period, against the period before'),
            ],
            ShoppingAssistantPerformanceTabsEnum::CONVERSATIONS => [
                'title'   => __('Conversations'),
                'icon'    => 'fal fa-comments',
                'tooltip' => __('Every conversation of the period with what was shown, clicked, added and ordered'),
            ],
            ShoppingAssistantPerformanceTabsEnum::SHOPS => [
                'title'   => __('Shops / Budget'),
                'icon'    => 'fal fa-store',
                'tooltip' => __('Each website in the period: conversations, orders, revenue and conversion, with its AI budget this month'),
            ],
        };
    }
}

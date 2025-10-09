<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:31:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SysAdmin;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\SysAdmin\User;

enum UserTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case SHOWCASE     = 'showcase';
    case HISTORY      = 'history';
    case API_TOKENS   = 'api_tokens';


    public function blueprint(User $parent): array
    {
        $stats = $parent->stats;
        $totalApitokens = $stats->number_current_api_tokens + $stats->number_expired_api_tokens;
        return match ($this) {

            UserTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            UserTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            UserTabsEnum::API_TOKENS => [
                'title' => __('Api tokens') . ' (' . ($totalApitokens > 0 ? $totalApitokens : 0) . ')',
                'icon'  => 'fal fa-key',
                'type'  => 'icon',
            ],

        };
    }
}

<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\SysAdmin;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ApiTokenRetinaTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case API_TOKENS   = 'api_tokens';
    case HISTORY      = 'history';


    public function blueprint(): array
    {
        return match ($this) {

            ApiTokenRetinaTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ApiTokenRetinaTabsEnum::API_TOKENS => [
                'title' => __('api tokens'),
                'icon'  => 'fal fa-key',
                'type'  => 'icon',
            ],

        };
    }
}

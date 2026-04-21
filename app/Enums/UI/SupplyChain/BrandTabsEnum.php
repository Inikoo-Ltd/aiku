<?php

/*
 * author Louis Perez
 * created on 21-04-2026-13h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BrandTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case HISTORY = 'history';


    public function blueprint(): array
    {
        return match ($this) {
            SELF::HISTORY => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('History'),
                'icon'  => 'fal fa-clock',

            ],
            SELF::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}

<?php

/*
 * Author Louis Perez
 * Created on 19-06-2026-09h-49m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\UI\Goods;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BarcodeTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';

    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            // Left side
            self::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            // Right side
            self::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}

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

enum BarcodesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX = 'index';

    public function blueprint(): array
    {
        return match ($this) {
            self::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}

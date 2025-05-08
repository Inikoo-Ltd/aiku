<?php

/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-14h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TopUpTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE          = 'showcase';
    case DATA          = 'data';
    case HISTORY_NOTES = 'history_notes';

    public function blueprint(): array
    {
        return match ($this) {
            TopUpTabsEnum::DATA => [
                'title' => __('data'),
                'icon'  => 'fal fa-database',
                'type'  => 'icon',
                'align' => 'right',
            ],

            TopUpTabsEnum::HISTORY_NOTES => [
                'title' => __('history, notes'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            TopUpTabsEnum::SHOWCASE => [
                'title' => __('showcase'),
                'icon'  => 'fas fa-info-circle',
            ],
        };
    }
}

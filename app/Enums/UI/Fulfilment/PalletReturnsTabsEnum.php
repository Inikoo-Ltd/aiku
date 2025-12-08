<?php

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-16h-27m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PalletReturnsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case RETURNS = 'returns';
    case UPLOADS = 'uploads';

    public function blueprint(): array
    {
        return match ($this) {
            PalletReturnsTabsEnum::UPLOADS => [
                'title' => __('uploads'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            PalletReturnsTabsEnum::RETURNS => [
                'title' => __('returns'),
                'icon' => 'fal fa-sign-out-alt',
            ],
        };
    }
}

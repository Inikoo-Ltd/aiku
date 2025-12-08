<?php

/*
 * author Arya Permana - Kirin
 * created on 21-03-2025-09h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StandaloneFulfilmentInvoiceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMS = 'items';
    case HISTORY = 'history';
    case EMAIL = 'email';

    public function blueprint(): array
    {
        return match ($this) {
            StandaloneFulfilmentInvoiceTabsEnum::EMAIL => [
                'align' => 'right',
                'title' => __('Email'),
                'icon' => 'fal fa-envelope',
                'type' => 'icon',
            ],

            StandaloneFulfilmentInvoiceTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],

            StandaloneFulfilmentInvoiceTabsEnum::ITEMS => [
                'title' => __('Items'),
                'icon' => 'fal fa-bars',
            ],
        };
    }
}

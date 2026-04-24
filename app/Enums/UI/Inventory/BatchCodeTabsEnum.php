<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 24 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BatchCodeTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW        = 'overview';
    case DELIVERY_NOTES  = 'delivery_notes';

    public function blueprint(): array
    {
        return match ($this) {
            BatchCodeTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            BatchCodeTabsEnum::DELIVERY_NOTES => [
                'title' => __('Delivery Notes'),
                'icon'  => 'fal fa-truck',
            ],
        };
    }
}

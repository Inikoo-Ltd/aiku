<?php

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-15h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PalletDeliveriesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DELIVERIES = 'deliveries';
    case UPLOADS = 'uploads';

    public function blueprint(): array
    {
        return match ($this) {
            PalletDeliveriesTabsEnum::UPLOADS => [
                'title' => __('uploads'),
                'icon' => 'fal fa-upload',
                //    'type'  => 'icon',
                'align' => 'right',
            ],
            PalletDeliveriesTabsEnum::DELIVERIES => [
                'title' => __('deliveries'),
                'icon' => 'fal fa-truck-couch',
            ],
        };
    }
}

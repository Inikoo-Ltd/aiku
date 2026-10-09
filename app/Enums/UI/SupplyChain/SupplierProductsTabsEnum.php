<?php

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SupplierProductsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PRODUCTS = 'products';
    case UPLOADS  = 'uploads';

    public function blueprint(): array
    {
        return match ($this) {
            self::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-box-usd',
            ],
            self::UPLOADS => [
                'title'      => __('Upload history'),
                'icon'       => 'fal fa-upload',
                'icon_badge' => 'fal fa-clock',
                'type'       => 'icon',
                'align'      => 'right',
            ],
        };
    }
}

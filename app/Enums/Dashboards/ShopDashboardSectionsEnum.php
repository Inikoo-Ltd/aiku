<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dashboards;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;

enum ShopDashboardSectionsEnum: string
{
    use EnumHelperTrait;

    case TARGET = 'target';
    case CUSTOMERS = 'customers';
    case SALES_CHANNELS = 'sales_channels';
    case PLATFORMS = 'platforms';
    case TENDENCIES = 'tendencies';

    public function blueprint(): array
    {
        return match ($this) {
            ShopDashboardSectionsEnum::TARGET => [
                'title' => __('Target'),
                'icon'  => 'fal fa-bullseye-arrow',
            ],
            ShopDashboardSectionsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-user-friends',
            ],
            ShopDashboardSectionsEnum::SALES_CHANNELS => [
                'title' => __('Sales channels'),
                'icon'  => 'fal fa-code-branch',
            ],
            ShopDashboardSectionsEnum::PLATFORMS => [
                'title' => __('Platforms'),
                'icon'  => 'fal fa-plug',
            ],
            ShopDashboardSectionsEnum::TENDENCIES => [
                'title' => __('Tendencies'),
                'icon'  => 'fal fa-chart-line',
            ],
        };
    }

    /**
     * @return array<self>
     */
    public static function forShop(Shop $shop): array
    {
        return $shop->type === ShopTypeEnum::DROPSHIPPING
            ? [self::TARGET, self::SALES_CHANNELS, self::PLATFORMS, self::TENDENCIES]
            : [self::TARGET, self::CUSTOMERS, self::TENDENCIES];
    }

    public static function navigation(Shop $shop): array
    {
        return collect(self::forShop($shop))
            ->mapWithKeys(fn (self $section) => [$section->value => $section->blueprint()])
            ->all();
    }

    public static function current(Shop $shop, array $userSettings): string
    {
        $saved = $userSettings['shop_dashboard_section'] ?? null;

        return in_array(self::tryFrom((string) $saved), self::forShop($shop), true) ? $saved : self::TARGET->value;
    }
}

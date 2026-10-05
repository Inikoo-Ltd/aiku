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
    case SALES = 'sales';
    case SALES_ANALYSIS = 'sales_analysis';
    case CUSTOMERS = 'customers';
    case SALES_CHANNELS = 'sales_channels';
    case PLATFORMS = 'platforms';
    case MARKETING = 'marketing';

    public function blueprint(): array
    {
        return match ($this) {
            ShopDashboardSectionsEnum::TARGET => [
                'title' => __('Target'),
                'icon'  => 'fal fa-bullseye-arrow',
            ],
            ShopDashboardSectionsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-chart-bar',
            ],
            ShopDashboardSectionsEnum::SALES_ANALYSIS => [
                'title' => __('Sales analysis'),
                'icon'  => 'fal fa-chart-line',
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
            ShopDashboardSectionsEnum::MARKETING => [
                'title' => __('Marketing'),
                'icon'  => 'fal fa-bullhorn',
            ],
        };
    }

    /**
     * @return array<self>
     */
    public static function forShop(Shop $shop): array
    {
        return $shop->type === ShopTypeEnum::DROPSHIPPING
            ? [self::TARGET, self::SALES, self::SALES_ANALYSIS, self::SALES_CHANNELS, self::PLATFORMS, self::MARKETING]
            : [self::TARGET, self::SALES, self::SALES_ANALYSIS, self::CUSTOMERS, self::MARKETING];
    }

    public static function navigation(Shop $shop): array
    {
        return collect(self::forShop($shop))
            ->mapWithKeys(fn (self $section) => [$section->value => $section->blueprint()])
            ->all();
    }

    public static function current(Shop $shop, array $userSettings, ?string $requested = null): string
    {
        foreach ([$requested, $userSettings['shop_dashboard_section'] ?? null] as $candidate) {
            if (in_array(self::tryFrom((string) $candidate), self::forShop($shop), true)) {
                return $candidate;
            }
        }

        return self::TARGET->value;
    }
}

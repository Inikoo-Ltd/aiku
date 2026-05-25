<?php

namespace App\Enums\Dashboards;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderTopCustomersSalesResource;
use App\Http\Resources\Dashboards\DashboardPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTopCustomersSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalTopCustomersSalesResource;
use App\Models\Catalogue\Shop;

enum ShopDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case BRANDS = 'brands';
    case DS_PLATFORMS = 'ds_platforms';
    case TOP_CUSTOMERS = 'top_customers';

    public function blueprint(): array
    {
        return match ($this) {
            ShopDashboardSalesTableTabsEnum::BRANDS => [
                'title' => __('Brands'),
                'icon'  => 'fal fa-copyright',
            ],
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => [
                'title' => __('DS Platforms'),
                'icon'  => 'fal fa-code-branch',
            ],
            ShopDashboardSalesTableTabsEnum::TOP_CUSTOMERS => [
                'title' => __('Top Customers'),
                'icon'  => 'fal fa-trophy',
            ],
        };
    }

    public function table(Shop $shop, array $timeSeriesData = []): array
    {
        if ($this === self::TOP_CUSTOMERS) {
            $topCustomers = $timeSeriesData['topCustomers'] ?? [];

            return [
                'header' => json_decode(DashboardHeaderTopCustomersSalesResource::make($shop)->toJson(), true),
                'body'   => json_decode(DashboardTopCustomersSalesResource::collection($topCustomers)->toJson(), true),
                'totals' => json_decode(DashboardTotalTopCustomersSalesResource::make($topCustomers)->toJson(), true),
            ];
        }

        $brandTimeSeriesStats    = $timeSeriesData['brands'] ?? [];
        $platformTimeSeriesStats = $timeSeriesData['platforms'] ?? [];

        $header = match ($this) {
            ShopDashboardSalesTableTabsEnum::BRANDS       => json_decode(DashboardHeaderBrandSalesResource::make($shop)->toJson(), true),
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => json_decode(DashboardHeaderPlatformSalesResource::make($shop)->toJson(), true),
        };

        $body = match ($this) {
            ShopDashboardSalesTableTabsEnum::BRANDS       => json_decode(DashboardBrandSalesResource::collection($brandTimeSeriesStats)->toJson(), true),
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => json_decode(DashboardPlatformSalesResource::collection($platformTimeSeriesStats)->toJson(), true),
        };

        $totals = match ($this) {
            ShopDashboardSalesTableTabsEnum::BRANDS       => json_decode(DashboardTotalBrandSalesResource::make($brandTimeSeriesStats)->toJson(), true),
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => json_decode(DashboardTotalPlatformSalesResource::make($platformTimeSeriesStats)->toJson(), true),
        };

        return [
            'header' => $header,
            'body'   => $body,
            'totals' => $totals,
        ];
    }

    public static function navigation(Shop $shop): array
    {
        return collect(self::cases())
            ->filter(function ($case) use ($shop) {
                if ($case === self::DS_PLATFORMS) {
                    return $shop->type->value === 'dropshipping';
                }
                return true;
            })
            ->mapWithKeys(fn ($case) => [$case->value => $case->blueprint()])
            ->all();
    }

    public static function tables(Shop $shop, array $timeSeriesData = []): array
    {
        return collect(self::cases())
            ->filter(function ($case) use ($shop) {
                if ($case === self::DS_PLATFORMS) {
                    return $shop->type->value === 'dropshipping';
                }
                return true;
            })
            ->mapWithKeys(fn ($case) => [$case->value => $case->table($shop, $timeSeriesData)])
            ->all();
    }

    public static function tablesForTabs(Shop $shop, array $timeSeriesData, array $tabs): array
    {
        return collect($tabs)
            ->map(function ($tab) {
                if ($tab instanceof self) {
                    return $tab;
                }
                return self::tryFrom((string) $tab);
            })
            ->filter()
            ->mapWithKeys(fn (self $tab) => [$tab->value => $tab->table($shop, $timeSeriesData)])
            ->filter()
            ->all();
    }
}

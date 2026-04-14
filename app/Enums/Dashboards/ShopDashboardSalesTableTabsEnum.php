<?php

namespace App\Enums\Dashboards;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalPlatformSalesResource;
use App\Models\Catalogue\Shop;

enum ShopDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case BRANDS = 'brands';
    case DS_PLATFORMS = 'ds_platforms';

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
        };
    }

    public function table(Shop $shop, array $timeSeriesData = []): array
    {
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
}

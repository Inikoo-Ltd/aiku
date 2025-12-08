<?php

namespace App\Enums\Dashboards;

use App\Actions\Dashboard\IndexPlatformSalesTable;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardHeaderPlatformSalesResource;
use App\Models\Catalogue\Shop;

enum ShopDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DS_PLATFORMS = 'ds_platforms';

    public function blueprint(): array
    {
        return match ($this) {
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => [
                'title' => __('DS Platforms'),
                'icon' => 'fal fa-code-branch',
            ]
        };
    }

    public function table(Shop $shop): array
    {
        $header = match ($this) {
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => json_decode(DashboardHeaderPlatformSalesResource::make($shop)->toJson(), true)
        };

        $body = match ($this) {
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => IndexPlatformSalesTable::make()->action($shop)
        };

        $totals = match ($this) {
            ShopDashboardSalesTableTabsEnum::DS_PLATFORMS => IndexPlatformSalesTable::make()->total($shop)
        };

        return [
            'header' => $header,
            'body' => $body,
            'totals' => $totals,
        ];
    }

    public static function tables(Shop $shop): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($shop) {
            return [$case->value => $case->table($shop)];
        })->all();
    }
}

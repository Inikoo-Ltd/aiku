<?php

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\Dashboards\ShopDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetShopDashboardTabData extends OrgAction
{
    use WithPerformanceDateResolution;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromShop($shop, $request);

        $tab = ShopDashboardSalesTableTabsEnum::tryFrom((string) $request->query('tab'));
        if (!$tab) {
            return response()->json([
                'message' => __('Invalid tab'),
            ], 422);
        }

        $userSettings     = $request->user()->settings;
        $intervalParam    = $request->query('interval');
        $savedInterval    = DateIntervalEnum::tryFrom((string) ($intervalParam ?? Arr::get($userSettings, 'selected_interval', 'all'))) ?? DateIntervalEnum::ALL;
        $performanceDates = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $timeSeriesData = GetShopDashboardTimeSeriesData::run($shop, $performanceDates[0], $performanceDates[1]);

        $table = $tab->table($shop, $timeSeriesData);

        return response()->json([
            'tab'   => $tab->value,
            'table' => $table,
        ]);
    }
}

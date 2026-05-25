<?php

namespace App\Actions\UI\Dashboards;

use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardTopCustomersLimitSettings;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\Dashboards\GroupDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetGroupDashboardTabData extends OrgAction
{
    use WithPerformanceDateResolution;
    use WithDashboardTopCustomersLimitSettings;

    public function asController(ActionRequest $request): JsonResponse
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        $tab = GroupDashboardSalesTableTabsEnum::tryFrom((string) $request->query('tab'));
        if (!$tab) {
            return response()->json([
                'message' => __('Invalid tab'),
            ], 422);
        }

        $userSettings = $request->user()->settings;
        $intervalParam = $request->query('interval');
        $savedInterval = DateIntervalEnum::tryFrom((string) ($intervalParam ?? Arr::get($userSettings, 'selected_interval', 'all'))) ?? DateIntervalEnum::ALL;
        $performanceDates = $this->resolvePerformanceDates($savedInterval, $userSettings);
        $topCustomersLimit = $this->dashboardTopCustomersLimitSettings($userSettings)['value'];

        $timeSeriesData = GetGroupDashboardTimeSeriesData::run($group, $performanceDates[0], $performanceDates[1], null, $topCustomersLimit);

        $table = $tab->table($group, $timeSeriesData);
        $tableSecondBlock = $tab->table($group, $timeSeriesData, true);

        return response()->json([
            'tab' => $tab->value,
            'table' => $table,
            'table_2' => empty($tableSecondBlock) ? null : $tableSecondBlock,
        ]);
    }
}

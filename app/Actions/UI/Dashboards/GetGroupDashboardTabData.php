<?php

namespace App\Actions\UI\Dashboards;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Enums\Dashboards\GroupDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetGroupDashboardTabData extends OrgAction
{
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
        $savedInterval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        $performanceDates = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $timeSeriesData = GetGroupDashboardTimeSeriesData::run($group, $performanceDates[0], $performanceDates[1]);

        $table = $tab->table($group, $timeSeriesData);
        $tableSecondBlock = $tab->table($group, $timeSeriesData, true);

        return response()->json([
            'tab' => $tab->value,
            'table' => $table,
            'table_2' => empty($tableSecondBlock) ? null : $tableSecondBlock,
        ]);
    }

    private function resolvePerformanceDates(DateIntervalEnum $savedInterval, array $userSettings): array
    {
        $performanceDates = [null, null];

        if ($savedInterval === DateIntervalEnum::CUSTOM) {
            $rangeInterval = Arr::get($userSettings, 'range_interval', '');
            if ($rangeInterval) {
                $dates = explode('-', $rangeInterval);
                if (count($dates) === 2) {
                    $performanceDates = [$dates[0], $dates[1]];
                }
            }

            return $performanceDates;
        }

        if ($savedInterval === DateIntervalEnum::ALL) {
            return $performanceDates;
        }

        $intervalString = DashboardIntervalFilters::run($savedInterval);
        if (!$intervalString) {
            return $performanceDates;
        }

        $dates = explode('-', $intervalString);
        if (count($dates) !== 2) {
            return $performanceDates;
        }

        return [$dates[0], $dates[1]];
    }
}

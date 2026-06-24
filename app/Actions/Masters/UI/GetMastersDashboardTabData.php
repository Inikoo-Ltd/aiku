<?php

namespace App\Actions\Masters\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\Dashboards\MastersDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetMastersDashboardTabData extends OrgAction
{
    use WithMastersAuthorisation;
    use WithPerformanceDateResolution;

    public function asController(ActionRequest $request): JsonResponse
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        $tab = MastersDashboardSalesTableTabsEnum::tryFrom((string) $request->query('tab'));
        if (!$tab) {
            return response()->json([
                'message' => __('Invalid tab'),
            ], 422);
        }

        $userSettings     = $request->user()->settings;
        $savedInterval    = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        $performanceDates = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $timeSeriesData = GetMastersDashboardTimeSeriesData::run($group, $performanceDates[0], $performanceDates[1]);

        $table = $tab->table($group, $timeSeriesData);

        return response()->json([
            'tab'   => $tab->value,
            'table' => $table,
        ]);
    }
}

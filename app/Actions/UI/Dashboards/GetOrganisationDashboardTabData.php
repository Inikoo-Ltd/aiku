<?php

namespace App\Actions\UI\Dashboards;

use App\Actions\Dashboard\GetOrganisationDashboardTimeSeriesData;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardPartnersTypeSettings;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\Dashboards\OrganisationDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetOrganisationDashboardTabData extends OrgAction
{
    use WithDashboardPartnersTypeSettings;
    use WithPerformanceDateResolution;

    public function asController(Organisation $organisation, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        $tab = OrganisationDashboardSalesTableTabsEnum::tryFrom((string) $request->query('tab'));
        if (!$tab) {
            return response()->json([
                'message' => __('Invalid tab'),
            ], 422);
        }

        $userSettings = $request->user()->settings;
        $intervalParam = $request->query('interval');
        $savedInterval = DateIntervalEnum::tryFrom((string) ($intervalParam ?? Arr::get($userSettings, 'selected_interval', 'all'))) ?? DateIntervalEnum::ALL;
        $performanceDates = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $timeSeriesData = GetOrganisationDashboardTimeSeriesData::run($organisation, $performanceDates[0], $performanceDates[1], null, $this->dashboardIncludesPartners($userSettings));

        $table = $tab->table($organisation, $timeSeriesData);

        return response()->json([
            'tab'   => $tab->value,
            'table' => $table,
        ]);
    }
}

<?php

namespace App\Actions\Helpers\Dashboard;

use App\Actions\Catalogue\Shop\UI\GetShopDashboardTimeSeriesData;
use App\Actions\Dashboard\GetOrganisationDashboardTimeSeriesData;
use App\Actions\UI\Dashboards\GetGroupDashboardTimeSeriesData;
use App\Models\SysAdmin\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakDashboardTimeSeriesCache
{
    use AsAction;

    public function handle(): void
    {
        foreach (Group::all() as $group) {
            GetGroupDashboardTimeSeriesData::clearCache($group);
            foreach ($group->organisations as $organisation) {
                GetOrganisationDashboardTimeSeriesData::clearCache($organisation);
                foreach ($organisation->shops as $shop) {
                    GetShopDashboardTimeSeriesData::clearCache($shop);
                }
            }
        }

        if (!Schema::hasTable('dashboard_time_series_aggregates')) {
            return;
        }

        DB::table('dashboard_time_series_aggregates')->truncate();
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->handle();

        return back();
    }
}

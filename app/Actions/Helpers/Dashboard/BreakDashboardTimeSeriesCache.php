<?php

namespace App\Actions\Helpers\Dashboard;

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

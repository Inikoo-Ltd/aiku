<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Fri, 13 Feb 2026 16:30:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\IntrastatExportTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoIntrastatExportTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'intrastat-export:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Organisation::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    protected function modifyQuery(Builder $query): Builder
    {
        return $query->where('type', OrganisationTypeEnum::SHOP->value);
    }

    public function handle(?int $organisationId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$organisationId) {
            return;
        }

        $organisation = Organisation::find($organisationId);

        if (!$organisation) {
            return;
        }

        if (!$from || !$to) {
            $dates = DB::connection('aiku_no_sticky')->table('delivery_notes')->where('organisation_id', $organisation->id)->whereNotNull('dispatched_at')->selectRaw('MIN(dispatched_at) as min_date, MAX(dispatched_at) as max_date')->first();

            if (!$dates || !$dates->min_date || !$dates->max_date) {
                return;
            }

            $from = $from ?? Carbon::parse($dates->min_date)->toDateString();
            $to   = $to ?? Carbon::parse($dates->max_date)->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessIntrastatExportTimeSeriesRecords::dispatch($organisation->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessIntrastatExportTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
            }
        }
    }

}

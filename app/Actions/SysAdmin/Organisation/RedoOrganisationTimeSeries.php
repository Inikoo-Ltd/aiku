<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoOrganisationTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'sales';
    public string $commandSignature = 'organisations:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Organisation::class;
    }

    public function getJobUniqueId(?int $organisationId, ?string $from, ?string $to): string
    {
        return $organisationId ?? 'empty'.'_'.$from.'_'.$to;
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
            $dates = collect([
                DB::table('invoices')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
                DB::table('customers')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(registered_at) as min_date, MAX(registered_at) as max_date')->first(),
            ]);

            $firstActivityDate = $dates->pluck('min_date')->filter()->min();
            $lastActivityDate  = $dates->pluck('max_date')->filter()->max();

            if (!$firstActivityDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstActivityDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastActivityDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOrganisationTimeSeriesRecords::dispatch($organisation->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOrganisationTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
            }
        }
    }

}

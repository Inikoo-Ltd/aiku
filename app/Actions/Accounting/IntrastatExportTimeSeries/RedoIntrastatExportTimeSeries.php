<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:30:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\IntrastatExportTimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoIntrastatExportTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'intrastat-export:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Organisation $organisation, bool $async = false): void
    {
        $dates = DB::table('delivery_notes')->where('organisation_id', $organisation->id)->whereNotNull('dispatched_at')->selectRaw('MIN(dispatched_at) as min_date, MAX(dispatched_at) as max_date')->first();

        if (!$dates || !$dates->min_date || !$dates->max_date) {
            return;
        }

        $from = Carbon::parse($dates->min_date)->toDateString();
        $to   = Carbon::parse($dates->max_date)->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessIntrastatExportTimeSeriesRecords::dispatch($organisation->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessIntrastatExportTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Organisation::where('type', OrganisationTypeEnum::SHOP->value)->get()->each(function (Organisation $organisation) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessIntrastatExportTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP->value)->get();

        $bar = $command->getOutput()->createProgressBar($organisations->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($organisations as $organisation) {
            try {
                $this->handle($organisation, $async);
            } catch (Throwable $e) {
                $command->error($e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $command->info('');

        return 0;
    }
}

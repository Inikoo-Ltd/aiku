<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoOrganisationTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'organisations:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Organisation $organisation, bool $async = false): void
    {
        $dates = collect([
            DB::table('invoices')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
            DB::table('orders')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')->first(),
            DB::table('delivery_notes')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
            DB::table('customers')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(registered_at) as min_date, MAX(registered_at) as max_date')->first(),
        ]);

        $firstActivityDate = $dates->pluck('min_date')->filter()->min();
        $lastActivityDate  = $dates->pluck('max_date')->filter()->max();

        if (!$firstActivityDate) {
            return;
        }

        $from = Carbon::parse($firstActivityDate)->toDateString();
        $to   = Carbon::parse($lastActivityDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOrganisationTimeSeriesRecords::dispatch($organisation->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOrganisationTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Organisation::where('type', OrganisationTypeEnum::SHOP->value)->get()->each(function (Organisation $organisation) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessOrganisationTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
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

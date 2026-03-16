<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoOrganisationTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long';
    public string $commandSignature = 'organisations:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Organisation::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Organisation $organisation, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if (!$from || !$to) {
            $dates = collect([
                DB::table('invoices')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
                // DB::table('orders')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')->first(),
                // DB::table('delivery_notes')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
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

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->where('type', OrganisationTypeEnum::SHOP->value)->orderBy('id', 'desc');

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                $model    = (new $this->model());
                $instance = $this->hasSoftDeletes($model)
                    ? $model->withTrashed()->find($modelId->id)
                    : $model->find($modelId->id);

                try {
                    $this->handle($instance, false, $from, $to);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());
        $tableName = (new $this->model())->getTable();
        $query     = $this->prepareQuery($tableName, $command);
        $query->where('type', OrganisationTypeEnum::SHOP->value);
        $count = $query->count();
        $bar   = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($bar, $command) {
            foreach ($modelsData as $modelId) {
                $model    = (new $this->model());
                $instance = $this->hasSoftDeletes($model)
                    ? $model->withTrashed()->find($modelId->id)
                    : $model->find($modelId->id);

                try {
                    $this->handle($instance, (bool) $command->option('async'), $command->option('from'), $command->option('to'));
                } catch (Throwable $e) {
                    $command->error($e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');

        return 0;
    }
}

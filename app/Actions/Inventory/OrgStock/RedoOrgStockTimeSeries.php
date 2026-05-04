<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoOrgStockTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long-slave';
    public string $commandSignature = 'org-stocks:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(OrgStock $orgStock, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if (!$from || !$to) {
            $dateRange = DB::connection('aiku_no_sticky')->table('invoice_transactions')
                ->join('invoice_transaction_has_org_stocks', 'invoice_transaction_has_org_stocks.invoice_transaction_id', '=', 'invoice_transactions.id')
                ->where('invoice_transaction_has_org_stocks.org_stock_id', $orgStock->id)
                ->whereNull('invoice_transactions.deleted_at')
                ->selectRaw('MIN(invoice_transactions.date) as first_date, MAX(invoice_transactions.date) as last_date')
                ->first();

            if (!$dateRange?->first_date) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange->first_date)->toDateString();
            $to   = $to ?? Carbon::parse($dateRange->last_date ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOrgStockTimeSeriesRecords::dispatch($orgStock->id, $frequency, $from, $to)->delay(300);
            } else {
                ProcessOrgStockTimeSeriesRecords::run($orgStock->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($from, $to) {
            $ids   = $modelsData->pluck('id')->all();
            $model = new $this->model();
            $instances = $this->hasSoftDeletes($model)
                ? $model->withTrashed()->whereIn('id', $ids)->get()->keyBy('id')
                : $model->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($modelsData as $modelId) {
                $instance = $instances->get($modelId->id);
                if (!$instance) {
                    continue;
                }

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
        $count     = $query->count();
        $bar       = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($bar, $command) {
            $ids   = $modelsData->pluck('id')->all();
            $model = new $this->model();
            $instances = $this->hasSoftDeletes($model)
                ? $model->withTrashed()->whereIn('id', $ids)->get()->keyBy('id')
                : $model->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($modelsData as $modelId) {
                $instance = $instances->get($modelId->id);
                if (!$instance) {
                    $bar->advance();
                    continue;
                }

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

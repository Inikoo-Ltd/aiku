<?php

namespace App\Actions\Helpers\Brand;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Helpers\Brand;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoBrandTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long';
    public string $commandSignature = 'brands:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Brand::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Brand $brand, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if (!$from || !$to) {
            $dateRange = DB::table('invoice_transactions')
                ->join('invoice_transaction_has_trade_units', 'invoice_transaction_has_trade_units.invoice_transaction_id', '=', 'invoice_transactions.id')
                ->join('model_has_brands', function ($join) use ($brand) {
                    $join->on('model_has_brands.model_id', '=', 'invoice_transaction_has_trade_units.trade_unit_id')
                         ->where('model_has_brands.model_type', '=', 'TradeUnit')
                         ->where('model_has_brands.brand_id', '=', $brand->id);
                })
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
                ProcessBrandTimeSeriesRecords::dispatch($brand->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessBrandTimeSeriesRecords::run($brand->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($from, $to) {
            $ids       = $modelsData->pluck('id')->all();
            $model     = new $this->model();
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
            $ids       = $modelsData->pluck('id')->all();
            $model     = new $this->model();
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

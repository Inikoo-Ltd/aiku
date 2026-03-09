<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoOfferTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long';
    public string $commandSignature = 'offers:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Offer::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Offer $offer, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if ($offer->state == OfferStateEnum::IN_PROCESS) {
            return;
        }

        if (!$from || !$to) {
            $firstTransactionDate = DB::table('invoice_transactions')
                ->whereExists(function ($query) use ($offer) {
                    $query->select(DB::raw(1))
                          ->from('transaction_has_offer_allowances')
                          ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                          ->where('transaction_has_offer_allowances.offer_id', $offer->id);
                })
                ->whereNull('deleted_at')
                ->min('date');

            $lastTransactionDate = DB::table('invoice_transactions')
                ->whereExists(function ($query) use ($offer) {
                    $query->select(DB::raw(1))
                          ->from('transaction_has_offer_allowances')
                          ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                          ->where('transaction_has_offer_allowances.offer_id', $offer->id);
                })
                ->whereNull('deleted_at')
                ->max('date');

            if (!$firstTransactionDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstTransactionDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastTransactionDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOfferTimeSeriesRecords::dispatch($offer->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOfferTimeSeriesRecords::run($offer->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

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
        $count     = $query->count();
        $bar       = $command->getOutput()->createProgressBar($count);
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

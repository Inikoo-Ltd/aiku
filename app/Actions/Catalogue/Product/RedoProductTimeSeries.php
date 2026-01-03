<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoProductTimeSeries
{
    use WithHydrateCommand;


    public string $commandSignature = 'products:redo_time_series  {organisations?*} {--S|shop= shop slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model       = Product::class;
    }

    public function handle(Product $product, array $frequencies, Command $command = null, bool $async = true): void
    {

        $from = null;
        $firstInvoicedDate = DB::table('invoice_transactions')->where('asset_id', $product->asset_id)->min('date');

        if ($firstInvoicedDate && ($firstInvoicedDate < $product->created_at)) {
            $product->update(['created_at' => $firstInvoicedDate]);
        }


        if ($product->created_at) {
            $from = $product->created_at->toDateString();
        }

        if ($product->state == ProductStateEnum::IN_PROCESS) {
            return;
        }

        if ($product->state == ProductStateEnum::ACTIVE || $product->state == ProductStateEnum::DISCONTINUING) {
            $to = now()->toDateString();
        } elseif ($product->state == ProductStateEnum::DISCONTINUED) {
            $to = $product->discontinued_at;
            $lastInvoicedDate = DB::table('invoice_transactions')
                ->where('asset_id', $product->id)
                ->max('date');
            if ($lastInvoicedDate && (!$to || $lastInvoicedDate > $to)) {
                $to = $lastInvoicedDate;
                $product->update(['discontinued_at' => $to]);
            }
            $to = $to->toDateString();
        } else {
            $to = DB::table('invoice_transactions')
                ->where('asset_id', $product->id)
                ->max('date');
            if (!$to) {
                return;
            }
            $to = $to->toDateString();
        }

        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessAssetTimeSeriesRecords::dispatch($product->asset_id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessAssetTimeSeriesRecords::run($product->asset_id, $frequency, $from, $to);
                }
            }
        }
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

        try {
            $frequencyOption = $command->option('frequency');

            if ($frequencyOption === 'all') {
                $frequencies = TimeSeriesFrequencyEnum::cases();
            } else {
                $frequencies = [
                    TimeSeriesFrequencyEnum::from($frequencyOption)
                ];
            }
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $query->chunk(
            1000,
            function (\Illuminate\Support\Collection $modelsData) use ($bar, $command, $frequencies) {
                foreach ($modelsData as $modelId) {
                    if ($this->modelAsHandleArg) {
                        $model = (new $this->model());
                        if ($this->hasSoftDeletes($model)) {
                            $instance = $model->withTrashed()->find($modelId->id);
                        } else {
                            $instance = $model->find($modelId->id);
                        }
                    } else {
                        $instance = $modelId->id;
                    }

                    try {
                        $this->handle($instance, $frequencies, $command, $command->option('async'));
                    } catch (Throwable $e) {
                        $command->error($e->getMessage());
                    }
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->info("");

        return 0;
    }



}

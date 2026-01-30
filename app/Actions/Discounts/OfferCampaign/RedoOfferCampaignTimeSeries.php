<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoOfferCampaignTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'offer_campaigns:redo_time_series {organisations?*} {--S|shop= shop slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model = OfferCampaign::class;
    }

    public function handle(OfferCampaign $offerCampaign, array $frequencies, bool $async = true): void
    {
        if ($offerCampaign->state == OfferCampaignStateEnum::IN_PROCESS) {
            return;
        }

        $offerCampaignId = (string) $offerCampaign->id;

        $sql = "
            SELECT MIN(t.date) as first_used_at, MAX(t.date) as last_used_at
            FROM transaction_has_offer_allowances thoa
            JOIN transactions t ON thoa.transaction_id = t.id
            WHERE thoa.offer_campaign_id = ?
            AND t.deleted_at IS NULL
        ";

        $results = DB::select($sql, [$offerCampaignId]);
        $result  = $results[0];

        $firstUsedAt = $result->first_used_at ? Carbon::parse($result->first_used_at) : null;
        $lastUsedAt  = $result->last_used_at ? Carbon::parse($result->last_used_at) : null;

        $from = $offerCampaign->start_at ? Carbon::parse($offerCampaign->start_at) : null;
        if ($firstUsedAt && (!$from || $firstUsedAt->lessThan($from))) {
            $from = $firstUsedAt;
        }

        // If no start date and no usage, fallback to created_at
        if (!$from) {
            $from = $offerCampaign->created_at;
        }

        if ($offerCampaign->state == OfferCampaignStateEnum::ACTIVE) {
            $to = now();
        } else {
            // FINISHED or SUSPENDED
            $to = $offerCampaign->finish_at ? Carbon::parse($offerCampaign->finish_at) : null;

            if ($lastUsedAt && (!$to || $lastUsedAt->greaterThan($to))) {
                $to = $lastUsedAt;
            }
        }

        if (!$from || !$to) {
            return;
        }

        $fromStr = $from->toDateString();
        $toStr   = $to->toDateString();

        foreach ($frequencies as $frequency) {
            if ($async) {
                ProcessOfferCampaignTimeSeriesRecords::dispatch($offerCampaign->id, $frequency, $fromStr, $toStr)->onQueue('low-priority');
            } else {
                ProcessOfferCampaignTimeSeriesRecords::run($offerCampaign->id, $frequency, $fromStr, $toStr);
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
                        $this->handle($instance, $frequencies, $command->option('async'));
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

<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithResetTimeSeriesIntervals
{
    use AsAction;

    protected TimeSeriesFrequencyEnum $frequency;

    protected function getDateRangeForFrequency(): array
    {
        $now = Carbon::now('UTC');

        return match ($this->frequency) {
            TimeSeriesFrequencyEnum::DAILY => [
                'from' => $now->copy()->subDay()->startOfDay(),
                'to' => $now->copy()->subDay()->endOfDay(),
            ],
            TimeSeriesFrequencyEnum::WEEKLY => [
                'from' => $now->copy()->subWeek()->startOfWeek(),
                'to' => $now->copy()->subWeek()->endOfWeek(),
            ],
            TimeSeriesFrequencyEnum::MONTHLY => [
                'from' => $now->copy()->subMonth()->startOfMonth(),
                'to' => $now->copy()->subMonth()->endOfMonth(),
            ],
            TimeSeriesFrequencyEnum::QUARTERLY => [
                'from' => $now->copy()->subQuarter()->startOfQuarter(),
                'to' => $now->copy()->subQuarter()->endOfQuarter(),
            ],
            TimeSeriesFrequencyEnum::YEARLY => [
                'from' => $now->copy()->subYear()->startOfYear(),
                'to' => $now->copy()->subYear()->endOfYear(),
            ],
        };
    }

    protected function resetProductCategories(): void
    {
        foreach (ProductCategory::all() as $productCategory) {
            $timeSeries = $productCategory->timeSeries()
                ->where('frequency', $this->frequency)
                ->first();

            if (!$timeSeries) {
                continue;
            }

            $dateRange = $this->getDateRangeForFrequency();

            ProductCategoryHydrateTimeSeriesRecords::dispatch(
                $timeSeries->id,
                $dateRange['from'],
                $dateRange['to']
            );
        }
    }

    public function handle(): void
    {
        $this->resetProductCategories();
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}

<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateTimeSeriesRecords;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateTimeSeriesRecords;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateTimeSeriesRecords;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateTimeSeriesRecords;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Website;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithResetTimeSeries
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
        foreach (ProductCategory::whereNotIn('state', [ProductCategoryStateEnum::IN_PROCESS, ProductCategoryStateEnum::DISCONTINUED])->get() as $productCategory) {
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

    protected function resetProducts(): void
    {
        foreach (Product::whereNotIn('state', [ProductStateEnum::IN_PROCESS, ProductStateEnum::DISCONTINUED])->get() as $product) {
            $timeSeries = $product->timeSeries()
                ->where('frequency', $this->frequency)
                ->first();

            if (!$timeSeries) {
                continue;
            }

            $dateRange = $this->getDateRangeForFrequency();

            ProductHydrateTimeSeriesRecords::dispatch(
                $timeSeries->id,
                $dateRange['from'],
                $dateRange['to']
            );
        }
    }

    protected function resetCollections(): void
    {
        foreach (Collection::whereNotIn('state', [CollectionStateEnum::IN_PROCESS])->get() as $collection) {
            $timeSeries = $collection->timeSeries()
                ->where('frequency', $this->frequency)
                ->first();

            if (!$timeSeries) {
                continue;
            }

            $dateRange = $this->getDateRangeForFrequency();

            CollectionHydrateTimeSeriesRecords::dispatch(
                $timeSeries->id,
                $dateRange['from'],
                $dateRange['to']
            );
        }
    }

    protected function resetWebsites(): void
    {
        foreach (Website::whereNotIn('state', [WebsiteStateEnum::IN_PROCESS, WebsiteStateEnum::CLOSED])->get() as $website) {
            $timeSeries = $website->timeSeries()
                ->where('frequency', $this->frequency)
                ->first();

            if (!$timeSeries) {
                continue;
            }

            $dateRange = $this->getDateRangeForFrequency();

            WebsiteHydrateTimeSeriesRecords::dispatch(
                $timeSeries->id,
                $dateRange['from'],
                $dateRange['to']
            );
        }
    }

    public function handle(): void
    {
        $this->resetProductCategories();
        $this->resetProducts();
        $this->resetCollections();
        $this->resetWebsites();
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}

<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class EnsureProductCategoryTimeSeries
{
    use AsAction;

    public function handle(ProductCategory $productCategory): int
    {
        $created = 0;

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $exists = $productCategory->timeSeries()
                ->where('frequency', $frequency)
                ->exists();

            if (!$exists) {
                $productCategory->timeSeries()->create([
                    'frequency' => $frequency,
                ]);
                $created++;
            }
        }

        return $created;
    }
}

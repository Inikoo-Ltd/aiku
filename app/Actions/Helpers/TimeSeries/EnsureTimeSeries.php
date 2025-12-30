<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class EnsureTimeSeries
{
    use AsAction;

    public function handle(Model $model): int
    {
        if (!method_exists($model, 'timeSeries')) {
            throw new \InvalidArgumentException(
                sprintf('Model %s does not have a timeSeries relationship', get_class($model))
            );
        }

        $created = 0;

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $exists = $model->timeSeries()
                ->where('frequency', $frequency)
                ->exists();

            if (!$exists) {
                $model->timeSeries()->create([
                    'frequency' => $frequency,
                ]);
                $created++;
            }
        }

        return $created;
    }
}

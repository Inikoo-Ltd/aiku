<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jan 2026 22:20:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterAsset;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

trait SeedsTimeSeries
{
    public function asCommand(Command $command): void
    {
        $frequencyOption = $command->option('frequency');
        $models = $this->getModelsToSeed();

        if ($frequencyOption === 'all') {
            $frequencies = TimeSeriesFrequencyEnum::cases();
        } else {
            $frequencies = [TimeSeriesFrequencyEnum::from($frequencyOption)];
        }

        $totalDispatched = 0;

        foreach ($models as $model) {
            foreach ($frequencies as $frequency) {
                if ($this->handle($model, $frequency)) {
                    $totalDispatched++;
                }
            }
        }

        $command->info("Dispatched $totalDispatched time series seed jobs for " . $this->getModelFriendlyName() . ".");
    }

    public function handle(Asset|MasterAsset|Collection|ProductCategory $model, TimeSeriesFrequencyEnum $frequency): bool
    {
        $targetModel = $this->getTargetModel($model);

        if (!$targetModel) {
            return false;
        }

        EnsureTimeSeries::run($targetModel);

        $timeSeries = $targetModel->timeSeries()
            ->where('frequency', $frequency)
            ->first();

        if (!$timeSeries) {
            return false;
        }

        $from = Carbon::now('UTC')->subYear()->startOfYear();
        $to = Carbon::now('UTC')->endOfDay();




        $this->getHydratorClass()::dispatch($timeSeries->id, $from, $to)->onQueue('low-priority');

        return true;
    }

    abstract protected function getModelsToSeed(): Collection;

    abstract protected function getModelFriendlyName(): string;

    abstract protected function getHydratorClass(): string;


}

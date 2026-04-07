<?php

/*
 * Author: stewicca
 * Created: 2026-04-07
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Helpers\Brand\Hydrators;

use App\Models\Helpers\Brand;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class BrandHydrateTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:brands_trade_units {brandSlug?}';

    public function getJobUniqueId(Brand $brand): int
    {
        return $brand->id;
    }

    public function handle(Brand $brand): void
    {
        $brand->updateQuietly([
            'number_trade_units' => $brand->tradeUnits()->count(),
        ]);
    }

    public function asCommand(Command $command): void
    {
        $query = Brand::query();

        if ($command->argument('brandSlug')) {
            $query->where('slug', $command->argument('brandSlug'));
        }

        $count = $query->count();
        $bar   = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->orderBy('id')->chunk(100, function (Collection $brands) use ($bar) {
            foreach ($brands as $brand) {
                $this->handle($brand);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info("Hydrated $count brands.");
    }
}

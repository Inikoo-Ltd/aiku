<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Nov 2025 14:34:35 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateTagsFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'master_asset:hydrate-tags {master_asset?} {--chunk=1000}';

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return $masterAsset->id;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        $tagsFromTradeUnits = $masterAsset->tradeUnitTagsViaTradeUnits();

        $tags = [];
        foreach ($tagsFromTradeUnits as $tagFromTradeUnits) {
            if (isset($tagFromTradeUnits['id'])) {
                $tags[$tagFromTradeUnits['id']] = [
                ];
            }
        }

        $masterAsset->tags()->sync($tags);

    }

    public function asCommand(Command $command): int
    {
        $slug = $command->argument('master_asset');

        // If a slug is provided, process a single master asset
        if (!empty($slug)) {
            $masterAsset = MasterAsset::where('slug', $slug)->first();
            if (!$masterAsset) {
                $command->error("MasterAsset with slug [$slug] not found.");
                return 1;
            }

            $this->handle($masterAsset);
            $command->info("Hydrated tags for master asset slug [$slug].");
            return 0;
        }

        // Otherwise, process all master assets in chunks with a progress bar
        $chunkSizeOption = (int)($command->option('chunk') ?? 1000);
        $chunkSize       = $chunkSizeOption > 0 ? $chunkSizeOption : 1000;

        $total = (int) MasterAsset::count();
        if ($total === 0) {
            $command->warn('No master assets found to hydrate.');
            return 0;
        }

        $command->line("Hydrating tags for {$total} master assets in chunks of {$chunkSize}...");

        // Setup progress bar with ETA
        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('[%bar%] %percent:3s%% | %current%/%max% | Elapsed: %elapsed:6s% | Remaining: %remaining:6s% | ETA: %estimated:-6s%');
        $bar->setRedrawFrequency(max(1, (int) floor($total / 200)));
        $bar->start();

        $processed = 0;

        MasterAsset::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($assets) use (&$processed, $bar) {
                foreach ($assets as $asset) {
                    $this->handle($asset);
                    $processed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine(2);
        $command->info("Hydrated tags for {$processed} master assets.");
        return 0;
    }
}

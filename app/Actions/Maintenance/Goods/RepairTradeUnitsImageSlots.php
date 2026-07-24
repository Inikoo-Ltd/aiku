<?php

/*
 * Author: AW Advantage <dev@aw-advantage.com>
 * Created: Wed, 22 Jul 2026
 * Copyright (c) 2026, Dava Moreno
 */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Catalogue\Product\CloneProductImagesFromTradeUnits;
use App\Actions\Goods\TradeUnit\UpdateTradeUnitImages;
use App\Actions\Masters\MasterAsset\CloneMasterAssetImagesFromTradeUnits;
use App\Actions\Traits\WithImageUpdate;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

class RepairTradeUnitsImageSlots
{
    use AsAction;
    use WithImageUpdate;

    private const array ART_COLUMNS = [
        'art1_image_id',
        'art2_image_id',
        'art3_image_id',
        'art4_image_id',
        'art5_image_id',
    ];

    /**
     * @return array{trade_unit_id: int, fixed: int, columns: array<int, string>, overflowed: int}
     */
    public function handle(TradeUnit $tradeUnit, bool $dryRun = false, bool $forcePropagate = false): array
    {
        $mapping          = $this->imageTypeMapping();
        $subScopeToColumn = array_flip($mapping);

        $usedMediaIds = [];
        foreach (array_keys($mapping) as $column) {
            $value = $tradeUnit->getAttribute($column);
            if (! blank($value)) {
                $usedMediaIds[(int) $value] = true;
            }
        }

        $modelData = [];

        // Tagged images -> their named slot, only when that slot is still empty.
        foreach ($tradeUnit->images as $media) {
            $subScope = $media->pivot->sub_scope;

            if (! $subScope || ! isset($subScopeToColumn[$subScope])) {
                continue;
            }

            $column = $subScopeToColumn[$subScope];

            if (blank($tradeUnit->getAttribute($column)) && ! isset($modelData[$column])) {
                $modelData[$column]             = $media->id;
                $usedMediaIds[(int) $media->id] = true;
            }
        }

        $orphans = $tradeUnit->images
            ->filter(fn ($media) => blank($media->pivot->sub_scope) && ! isset($usedMediaIds[(int) $media->id]))
            ->sortBy([['created_at', 'desc'], ['id', 'desc']]);

        $overflowed = 0;
        foreach ($orphans as $orphan) {
            $target = null;
            foreach (self::ART_COLUMNS as $column) {
                if (blank($tradeUnit->getAttribute($column)) && ! isset($modelData[$column])) {
                    $target = $column;
                    break;
                }
            }

            if ($target === null) {
                $overflowed++;
                continue;
            }

            $modelData[$target]              = $orphan->id;
            $usedMediaIds[(int) $orphan->id] = true;
        }

        if (! $dryRun) {
            if (! empty($modelData)) {
                UpdateTradeUnitImages::run($tradeUnit, $modelData, false);
            }

            if (! empty($modelData) || $forcePropagate) {
                $this->propagateToDependants($tradeUnit);
            }
        }

        return [
            'trade_unit_id' => $tradeUnit->id,
            'fixed'         => count($modelData),
            'columns'       => array_keys($modelData),
            'overflowed'    => $overflowed,
        ];
    }

    private function propagateToDependants(TradeUnit $tradeUnit): void
    {
        foreach ($tradeUnit->masterAssets as $masterAsset) {
            if ($masterAsset->is_single_trade_unit && $masterAsset->follow_trade_unit_media) {
                CloneMasterAssetImagesFromTradeUnits::run($masterAsset);
            }
        }

        foreach ($tradeUnit->products as $product) {
            $followMaster = $product->masterProduct && ! $product->masterProduct->follow_trade_unit_media;

            if ($product->is_single_trade_unit && ! $followMaster) {
                CloneProductImagesFromTradeUnits::run($product);
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'trade_units:repair_image_slots {tradeUnit?} {--dry-run}';
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');

        if ($slug = $command->argument('tradeUnit')) {
            $tradeUnit = TradeUnit::where('slug', $slug)->first();

            if (! $tradeUnit) {
                $command->error("Trade Unit '$slug' not found.");

                return Command::FAILURE;
            }

            $result = $this->handle($tradeUnit, $dryRun, forcePropagate: true);
            $command->info(($dryRun ? '[DRY RUN] ' : '') . "Trade Unit {$tradeUnit->id}: fixed {$result['fixed']} slot(s)" .
                ($result['fixed'] > 0 ? ' [' . implode(', ', $result['columns']) . ']' : '') .
                ($dryRun ? '' : ' — propagated to dependants') .
                ($result['overflowed'] > 0 ? " — {$result['overflowed']} orphan image(s) dropped, no free art slot" : ''));

            return Command::SUCCESS;
        }

        $total = TradeUnit::count();
        $command->info(($dryRun ? '[DRY RUN] ' : '') . "Repairing image slots for $total trade units...");

        $bar = new ProgressBar($command->getOutput(), $total);
        $bar->setFormat('verbose');
        $bar->start();

        $processed    = 0;
        $fixedSlots   = 0;
        $touchedUnits = 0;
        $overflowed   = 0;
        $failures     = [];

        TradeUnit::with('images')
            ->orderBy('id')
            ->chunkById(1000, function ($tradeUnits) use (&$processed, &$fixedSlots, &$touchedUnits, &$overflowed, &$failures, $dryRun, $command, $bar) {
                foreach ($tradeUnits as $tradeUnit) {
                    try {
                        $result = $this->handle($tradeUnit, $dryRun);

                        $overflowed += $result['overflowed'];

                        if ($result['fixed'] > 0) {
                            $fixedSlots += $result['fixed'];
                            $touchedUnits++;
                        }
                    } catch (Throwable $e) {
                        $failures[] = [$tradeUnit->slug ?? (string) $tradeUnit->id, $e->getMessage()];
                        $command->newLine();
                        $command->error("Trade Unit {$tradeUnit->id}: {$e->getMessage()}");
                    }

                    $processed++;
                    $bar->advance();
                }
            }, 'id');

        $bar->finish();
        $command->newLine(2);

        $command->info(($dryRun ? '[DRY RUN] ' : '') .
            "Processed $processed, fixed $fixedSlots slot(s) on $touchedUnits trade unit(s), " .
            "$overflowed orphan(s) dropped, " . count($failures) . " failed.");

        if (! empty($failures)) {
            $command->newLine();
            $command->warn('Failures:');
            $command->table(['Trade Unit', 'Error'], $failures);
        }

        return Command::SUCCESS;
    }
}

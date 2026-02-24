<?php

/*
 * author Louis Perez
 * created on 24-02-2026-11h-13m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Goods;

use App\Actions\Catalogue\Product\CloneProductImagesFromTradeUnits;
use App\Actions\Masters\MasterAsset\CloneMasterAssetImagesFromTradeUnits;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Transfers\Aurora\WithAuroraImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairTradeUnitVideosToChildren
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraImages;

    public function handle(TradeUnit $tradeUnit): void
    {
        foreach (
            DB::table('model_has_trade_units')
                ->select('model_type', 'model_id')
                ->where('trade_unit_id', $tradeUnit->id)
                ->whereIn('model_type', ['MasterAsset', 'Product'])
                ->get() as $modelsData
        ) {
            if ($modelsData->model_type == 'MasterAsset') {
                $masterAsset = MasterAsset::find($modelsData->model_id);
                if ($masterAsset && $masterAsset->is_single_trade_unit && $masterAsset->follow_trade_unit_media) {
                    CloneMasterAssetImagesFromTradeUnits::dispatch($masterAsset);
                }
            } elseif ($modelsData->model_type == 'Product') {
                $product = Product::find($modelsData->model_id);

                $followMaster = false;
                if ($product->masterProduct && !$product->masterProduct->follow_trade_unit_media) {
                    $followMaster = true;
                }

                if ($product && $product->is_single_trade_unit && !$followMaster) {
                    CloneProductImagesFromTradeUnits::run($product);
                }
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'trade_units:repair_children_videos {status}';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $query = TradeUnit::whereNotNull('video_url');
        if ($command->argument('status') == 'active') {
            $query->where('status', TradeUnitStatusEnum::ACTIVE);
        } else {
            $query->where('status', '!=', TradeUnitStatusEnum::ACTIVE);
        }
        $total = $query->count();

        $command->info("Repairing video_url for $total trade units...");
        $start     = microtime(true);
        $processed = 0;

        $bar = new ProgressBar($command->getOutput(), $total);
        $bar->setFormat('debug');
        $bar->start();

        $query
            ->orderBy('id')
            ->chunkById(1000, function ($tradeUnits) use (&$processed, $bar, $command) {
                foreach ($tradeUnits as $tradeUnit) {
                    $this->handle($tradeUnit);
                    $processed++;
                    $bar->advance();
                }
            }, 'id');

        $bar->finish();
        $command->newLine(2);
        $duration = microtime(true) - $start;
        $command->info("Done. Processed $processed/$total trade units in ".gmdate('H:i:s', (int)$duration).".");

        return 0;
    }

}

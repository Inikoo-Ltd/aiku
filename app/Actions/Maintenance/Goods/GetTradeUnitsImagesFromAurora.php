<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 19:16:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Catalogue\Product\CloneProductImagesFromTradeUnits;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateImages;
use App\Actions\Helpers\Media\SaveModelImages;
use App\Actions\Masters\MasterAsset\CloneMasterAssetImagesFromTradeUnits;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraImages;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class GetTradeUnitsImagesFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraImages;

    /**
     * @var \App\Transfers\AuroraOrganisationService|\App\Transfers\WowsbarOrganisationService|null
     */
    private \App\Transfers\WowsbarOrganisationService|null|AuroraOrganisationService $organisationSource;

    private $organisation;


    public function handle(TradeUnit $tradeUnit, Command $command): void
    {
        $productImages = [];
        $products      = $tradeUnit->products()->where('is_main', true)->where('shop_id', 1)->get();

        if ($products->count() > 1) {
            $products = $tradeUnit->products()->where('is_main', true)
                ->whereRaw("lower(code) = lower(?)", [$tradeUnit->code])
                ->where('shop_id', 1)->get();
        }


        //       foreach ($products as $product) {
        //           print $product->code."\n";
        //       }


        if ($products->count() == 1) {
            foreach ($products as $product) {
                $productImages = $this->fetchAuroraProductImages($product);
            }
        }

        if (count($productImages) == 0) {
            if ($tradeUnit->status == TradeUnitStatusEnum::ACTIVE) {
                $command->error("No images found for trade unit $tradeUnit->slug");
            }
        } else {
            //dd($tradeUnit->images);
            DB::table('model_has_media')->where('model_id', $tradeUnit->id)->where('model_type', 'TradeUnit')->delete();

            $tradeUnit->update([
                'image_id'                 => null,
                'front_image_id'           => null,
                '34_image_id'              => null,
                'left_image_id'            => null,
                'right_image_id'           => null,
                'back_image_id'            => null,
                'top_image_id'             => null,
                'bottom_image_id'          => null,
                'size_comparison_image_id' => null,
                'lifestyle_image_id'       => null,
            ]);


            $subScope = 'main';

        //    dd($productImages);

            foreach ($productImages as $imageData) {
                SaveModelImages::run(
                    model: $tradeUnit,
                    mediaData: [
                        'path'         => $imageData['image_path'],
                        'originalName' => $imageData['filename'],

                    ],
                    mediaScope: 'product_images',
                    modelHasMediaData: [
                        'created_at' => now(),
                        'updated_at' => now(),
                        'fetched_at' => now(),
                        'source_id'  => $imageData['source_id'],
                        'scope'      => 'photo',
                        'is_public'  => $imageData['is_public'],
                        'caption'    => $imageData['caption'] ?? '',
                        'position'   => $this->organisation->id * 1000 + $imageData['position'],
                        'sub_scope'  => $subScope,

                    ]
                );
                $subScope = null;
            }
            TradeUnitHydrateImages::run($tradeUnit);

            foreach ($tradeUnit->products as $product) {
                ModelHydrateSingleTradeUnits::run($product);
                $product->refresh();
                CloneProductImagesFromTradeUnits::run($product);
            }

            foreach ($tradeUnit->masterAssets as $masterAsset) {
                ModelHydrateSingleTradeUnits::run($masterAsset);
                $masterAsset->refresh();
                CloneMasterAssetImagesFromTradeUnits::run($masterAsset);
            }
        }
    }

    private function fetchAuroraProductImages(Product $product): array
    {
        $sourceData = $product->source_id;
        if (!$sourceData) {
            return [];
        }
        $sourceData = explode(':', $sourceData);

        $images = $this->getModelImagesCollection(
            'Product',
            $sourceData[1]
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });

        return $images->toArray();
    }


    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);
    }

    public function getCommandSignature(): string
    {
        return 'trade_units:get_images {status}';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $organisation       = Organisation::where('id', 1)->first();
        $this->organisation = $organisation;

        $this->setSource($organisation);


//        $tradeUnits = TradeUnit::find(10844);
//        $this->handle($tradeUnits, $command);
//        exit;

        if ($command->argument('status') == 'active') {
            $total = DB::table('trade_units')->where('status', TradeUnitStatusEnum::ACTIVE)->count();
        } else {
            $total = DB::table('trade_units')->where('status', '!=', TradeUnitStatusEnum::ACTIVE)->count();
        }


        $command->info("Repairing main image sub_scope for $total trade units...");
        $start     = microtime(true);
        $processed = 0;

        $bar = new ProgressBar($command->getOutput(), $total);
        $bar->setFormat('debug');
        $bar->start();

        $query = DB::table('trade_units')
            ->select('id');
        if ($command->argument('status') == 'active') {
            $query->where('status', TradeUnitStatusEnum::ACTIVE);
        } else {
            $query->where('status', '!=', TradeUnitStatusEnum::ACTIVE);
        }


        $query
            ->orderBy('id')
            ->chunkById(1000, function ($tradeUnitRows) use (&$processed, $bar, $command) {
                foreach ($tradeUnitRows as $row) {
                    $tradeUnit = TradeUnit::find($row->id);
                    if ($tradeUnit) {
                        $this->handle($tradeUnit, $command);
                    }
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

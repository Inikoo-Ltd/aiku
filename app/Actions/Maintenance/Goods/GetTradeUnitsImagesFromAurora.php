<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 19:16:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Goods;

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


    public function handle(TradeUnit $tradeUnit,Command $command): void
    {

        $productImages=[];
        $products=$tradeUnit->products()->where('is_main',true)->where('shop_id',1)->get();

        if($products->count()>1){

            $products=$tradeUnit->products()->where('is_main',true)
                ->whereRaw("lower(code) = lower(?)", [$tradeUnit->code])
                ->where('shop_id',1)->get();

        }


//       foreach ($products as $product) {
//           print $product->code."\n";
//       }


        if($products->count() ==1) {
            foreach ($products as $product) {

                $productImages = $this->fetchAuroraProductImages($product);
            }
        }

        if(count($productImages)==0){

            if($tradeUnit->status==TradeUnitStatusEnum::ACTIVE){
                $command->error("No images found for trade unit $tradeUnit->slug");
            }


        }else{
          //  $command->line("Found ".count($productImages)." images for trade unit $tradeUnit->slug");
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
        return 'trade_units:get_images';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {


        $organisation=Organisation::where('id',1)->first();
        $this->organisation=$organisation;

        $this->setSource($organisation);


//        $tradeUnits = TradeUnit::find(40526);
//        $this->handle($tradeUnits,$command);
//        exit;

        $total = DB::table('trade_units')->count();
        $command->info("Repairing main image sub_scope for $total trade units...");
//        $start = microtime(true);
//        $processed = 0;
//
//        $bar = new ProgressBar($command->getOutput(), $total);
//        $bar->setFormat('verbose');
//        $bar->start();

        DB::table('trade_units')
            ->select('id')
            ->orderBy('id')
            ->chunkById(1000, function ($tradeUnitRows) use (&$processed,$command) {
                foreach ($tradeUnitRows as $row) {
                    $tradeUnit = TradeUnit::find($row->id);
                    if ($tradeUnit) {
                        $this->handle($tradeUnit,$command);
                    }
                    $processed++;
                   // $bar->advance();
                }
            }, 'id');

//        $bar->finish();
//        $command->newLine(2);
//        $duration = microtime(true) - $start;
//        $command->info("Done. Processed $processed/$total trade units in ".gmdate('H:i:s', (int) $duration).".");

        return 0;
    }

}

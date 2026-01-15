<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jan 2026 18:38:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */




/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductDescriptionAndNameFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Shop $shop, Command $command): void
    {
        $organisation             = $shop->organisation;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);


        Product::query()
            ->where(
                'shop_id',
                $shop->id
            )
            ->whereNotNull('source_id')
            ->orderBy('id')
            ->chunkById(1000, function ($products) use ($command) {
                foreach ($products as $product) {
                    $description = '';


                    if ($product && $product->webpage && $product->webpage->source_id) {
                        $sourceData  = explode(':', $product->webpage->source_id);
                        $webpageData = DB::connection('aurora')->table('Page Store Dimension')->where('Page Key', $sourceData[1])->first();
                        if ($webpageData) {
                            $webpageData = $webpageData->{'Page Store Content Published Data'};
                            if ($webpageData) {
                                $webpageData    = json_decode($webpageData, true);

                                $productWebBlockdData = null;


                                foreach ($webpageData['blocks'] as $block) {
                                    if (Arr::get($block, 'type') == 'product') {
                                        $productWebBlockdData = $block;
                                        break;
                                    }
                                }



                                if ($productWebBlockdData) {

                                    $description = Arr::get($productWebBlockdData, 'text', '');

                                    $description = str_replace('<p><br></p>', '', $description);
                                    $description = str_replace('<p><br />\u003C/p>', '', $description); // handle potential escaped variant
                                    $description = str_replace('<p><br /></p>', '', $description);


                                }


                            }
                        }
                    }


                    if ($description) {
                        $product->update([
                            'description'             => $description,
                            'is_description_reviewed' => true
                        ]);
                        if ($product->wasChanged('description')) {
                            $command->info("Description changed $product->code");
                        }

                        $product->update(
                            [
                                'description_extra'             => '',
                                'is_description_extra_reviewed' => true
                            ]
                        );


                    }


                    $shopSourceData = explode(':', $product->shop->source_id);
                    $auroraStoreKey = $shopSourceData[1];

                    $auProductData = DB::connection('aurora')->table('Product Dimension')
                        ->where('Product Store Key', $auroraStoreKey)
                        ->whereRaw('LOWER(`Product Code`) = ?', [strtolower($product->code)])
                        ->first();
                    if ($auProductData) {
                        $product->update(
                            [
                                'name'             => $auProductData->{'Product Name'},
                                'is_name_reviewed' => true
                            ]
                        );
                        if ($product->wasChanged('name')) {
                            $command->info("Name changed $product->code $product->name");
                        }
                    }
                }
            }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:update_product_descriptions_from_aurora {shop}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();
        $this->handle($shop, $command);

        return 0;
    }

}

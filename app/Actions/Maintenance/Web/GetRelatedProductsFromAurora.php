<?php


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Web;

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 14:32:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


use AllowDynamicProperties;
use App\Actions\Masters\MasterProductCategory\SyncMasterProductCategoryRelatedMasterAssets;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

#[AllowDynamicProperties]
class GetRelatedProductsFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        $shop = Shop::where('slug', 'uk')->firstOrFail();

        $organisation             = $shop->organisation;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);


        foreach ($shop->productCategories as $family) {
            if ($family->type != ProductCategoryTypeEnum::FAMILY) {
                continue;
            }

            $masterFamily = MasterProductCategory::where('id', $family->master_product_category_id)->first();
            if (!$masterFamily) {
                continue;
            }


            $sourceData = $family->source_family_id;

            $masterAssets = [];

            if ($sourceData) {
                $sourceData     = explode(':', $sourceData);
                $auroraFamilyId = $sourceData[1];

                $auData = DB::connection('aurora')->table('Page Store Dimension')
                    ->where('Webpage Scope', 'Category Products')
                    ->where('Webpage Scope Key', $auroraFamilyId)
                    ->first();

                if (!$auData) {
                    continue;
                }


                $rawBlocks = $auData->{'Page Store Content Published Data'};

                $blocks = json_decode($rawBlocks, true);

                foreach (Arr::get($blocks, 'blocks', []) as $block) {
                    if (Arr::get($block, 'type') == 'products') {
                        foreach (Arr::get($block, 'items', []) as $productData) {
                            if (Arr::get($productData, 'type') == 'product') {
                                $product = Product::where('source_id', '1:'.Arr::get($productData, 'product_id'))->first();

                                if ($product && $product->master_product_id) {
                                    $masterAssets[] = $product->master_product_id;
                                }
                            }
                        }
                    }
                }

                SyncMasterProductCategoryRelatedMasterAssets::make()->action(
                    $masterFamily,
                    [
                        'master_asset_ids' => $masterAssets
                    ]
                );
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:get_aurora_related_products';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(): int
    {
        $this->handle();

        return 0;
    }

}

<?php


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Web;

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 14:32:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


use AllowDynamicProperties;
use App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProductCategories\SyncMasterProductCategoryRelatedMasterProductCategories;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

#[AllowDynamicProperties]
class GetRelatedFamiliesFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    /**
     * @throws \Exception
     */
    public function handle(Command $command): void
    {
        $shop = Shop::where('slug', 'uk')->firstOrFail();

        $organisation             = $shop->organisation;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);

        /** @var ProductCategory $family */
        foreach ($shop->productCategories()->get() as $family) {
            if ($family->type != ProductCategoryTypeEnum::FAMILY) {
                continue;
            }

            $masterFamily = MasterProductCategory::where('id', $family->master_product_category_id)->first();
            if (!$masterFamily) {
                continue;
            }


            $sourceData = $family->source_family_id;

            $masterFamilies = [];

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
                    if (Arr::get($block, 'type') == 'see_also') {

                        foreach (Arr::get($block, 'items', []) as $productData) {
                            if (Arr::get($productData, 'type') == 'category') {
                                $category = ProductCategory::where('source_family_id', '1:'.Arr::get($productData, 'category_key'))->first();

                                if ($category && $category->type == ProductCategoryTypeEnum::FAMILY &&   $category->master_product_category_id) {
                                    $masterFamilies[] = $category->master_product_category_id;
                                }
                            }
                        }
                    }
                }

                if (count($masterFamilies) == 0) {
                    $command->info('No related families found for '.$masterFamily->code);
                    continue;
                }

                $command->info('Master Family: '.$masterFamily->code);

                foreach ($masterFamilies as $relatedMasterFamilyId) {
                    $relatedMasterFamily = MasterProductCategory::find($relatedMasterFamilyId);
                    if ($relatedMasterFamily) {
                        $command->info('  Related Family: '.$relatedMasterFamily->code);
                    }
                }


                SyncMasterProductCategoryRelatedMasterProductCategories::make()->action(
                    $masterFamily,
                    [
                        'related_master_product_category_id' => $masterFamilies
                    ]
                );
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:get_aurora_related_families';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }

}

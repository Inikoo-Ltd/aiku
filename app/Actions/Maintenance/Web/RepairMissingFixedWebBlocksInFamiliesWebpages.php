<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 09:47:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInFamiliesWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        if ($webpage->model_type == 'ProductCategory') {
            /** @var ProductCategory $model */
            $model = $webpage->model;
            if ($model->type == ProductCategoryTypeEnum::FAMILY) {
                $this->processFamilyWebpages($webpage, $command);
            }
        }
    }


    protected function processFamilyWebpages(Webpage $webpage, Command $command): void
    {
        $shop = $webpage->shop;

        /** @var ProductCategory $family */
        $family = $webpage->model;


        //        foreach($webpage->webBlocks as $webblock) {
        //            print $webblock->webBlockType->code . "\n";
        //        }
        //        exit;

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'family');
        if (count($countFamilyWebBlock) > 0) {
            foreach ($countFamilyWebBlock as $webBlockData) {
                DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
            };
        }


        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'overview_aurora');

        if (count($countFamilyWebBlock) > 0) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 overview_aurora Web Block found');

            foreach ($countFamilyWebBlock as $webBlockData) {
                $layout       = json_decode($webBlockData->layout, true);
                $descriptions = Arr::get($layout, 'data.fieldValue.texts.values');

                $description = '';
                foreach ($descriptions as $descriptionData) {
                    $text = Arr::get($descriptionData, 'text');
                    if ($text) {
                        $description .= $text.' ';
                    }
                }
                $description = trim($description);

                if ($description && !$shop->is_aiku) {
                    $command->line('F: '.$family->id.' Family description updated');
                    $family->update(['description' => $description]);
                }

                DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
            };
        }

        $countFamilyDescriptionBlock = null;
        $familyDescriptionBlock = 'family-1';

        if ($command->option('alternative-design')) {
            $familyDescriptionBlock = 'family-2';

            $countFamilyDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'family-2');
            if (count($countFamilyDescriptionBlock) == 0) {
                $this->deleteWebBlocksByCode($webpage, 'family-1');
                $this->deleteWebBlocksByCode($webpage, 'family-3');
                $this->deleteWebBlocksByCode($webpage, 'family-3-extra-description');
                $this->createWebBlock($webpage, 'family-2');
                $this->createWebBlock($webpage, 'family-2-extra-description');
            }
        } else {
            $countFamilyDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'family-1');
            if (count($countFamilyDescriptionBlock) == 0) {
                $this->deleteWebBlocksByCode($webpage, 'family-2');
                $this->deleteWebBlocksByCode($webpage, 'family-2-extra-description');
                $this->deleteWebBlocksByCode($webpage, 'family-3');
                $this->deleteWebBlocksByCode($webpage, 'family-3-extra-description');

                $this->createWebBlock($webpage, 'family-1');
            }
        }

        // NEW LOGIC, PREVENT MULTIPLE SAME SCOPED WEB BLOCK UNDER SAME PAGE (HANDLES TEMPLATES)
        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes(), WebBlockTemplateEnum::LIST_PRODUCTS);

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'luigi-trends-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'luigi-trends-1');
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'luigi-last-seen-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'luigi-last-seen-1');
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'recommendation-customer-recently-bought-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'recommendation-customer-recently-bought-1');
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'recommendation-from-master');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'recommendation-from-master');
        }

        $countRelatedProductCategoryBlock = $this->getWebpageBlocksByType($webpage, 'recommendation-product-category-from-master');
        if (count($countRelatedProductCategoryBlock) == 0) {
            $this->createWebBlock($webpage, 'recommendation-product-category-from-master');
        }

        $webpage->refresh();

        $this->reorderFamilyPageBlocks($webpage, $familyDescriptionBlock);

        if ($command->option('hide-description')) {
            $this->setDescriptionWebBlockHidden($webpage);
        }
        $webpage->refresh();
        UpdateWebpageContent::run($webpage);

        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";

        if ($webpage->is_dirty) {
            if (in_array($family->state, [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])) {
                $command->line('Webpage '.$webpage->code.' is dirty, publishing after upgrade');
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'publish after upgrade',
                    ]
                );
            }
        }
    }

    public function setDescriptionWebBlockHidden(Webpage $webpage): void
    {
        $familyDescriptionWebBlock = $this->getWebpageBlocksByType($webpage, 'family-1')->first();

        if ($familyDescriptionWebBlock) {
            DB::table('model_has_web_blocks')
                ->where('id', $familyDescriptionWebBlock->model_has_web_blocks_id)
                ->update(['show' => false]);
        }

        UpdateWebpageContent::run($webpage);
    }

    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_families_webpages {website?} {--webpage_id=} {--hide-description} {--a|alternative-design}';

    public function asCommand(Command $command): void
    {
        $singleWebpageId = $command->option('webpage_id');

        if ($singleWebpageId) {
            $webpagesID = collect([(object)['id' => (int)$singleWebpageId]]);
        } else {
            $query = DB::table('webpages')->select('id')
            ->where('sub_type', 'family');
            if ($command->argument('website')) {
                $website   = Website::where('slug', $command->argument('website'))->first();
                $query->where('website_id', $website->id);
            }
            $webpagesID = $query->get();
        }

        $total   = count($webpagesID);
        $current = 1;
        foreach ($webpagesID as $webpageID) {
            print "[{$current}/{$total}] Webpage id: {$webpageID->id}\n";
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
            $current++;
        }
    }

}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 09:47:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
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
                $layout = json_decode($webBlockData->layout, true);
                $descriptions = Arr::get($layout, 'data.fieldValue.texts.values');

                $description = '';
                foreach ($descriptions as $descriptionData) {
                    $text = Arr::get($descriptionData, 'text');
                    if ($text) {
                        $description .= $text.' ';
                    }
                }
                $description = trim($description);

                if ($description) {
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


        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'family-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'family-1', $family);
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'products-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'products-1', $family);
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


    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_families_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('sub_type', 'family')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            $this->handle($webpage, $command);
        }
    }

}

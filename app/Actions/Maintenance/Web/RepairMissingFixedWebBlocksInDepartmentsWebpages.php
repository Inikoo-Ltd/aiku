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
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInDepartmentsWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        if ($webpage->model_type == 'ProductCategory') {
            /** @var ProductCategory $model */
            $model = $webpage->model;
            if ($model->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $this->processDepartmentWebpages($webpage, $command);
            }
        }
    }


    protected function processDepartmentWebpages(Webpage $webpage, Command $command): void
    {
        /** @var ProductCategory $department */
        $department = $webpage->model;


        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'department');
        if (count($countFamilyWebBlock) > 0) {
            foreach ($countFamilyWebBlock as $webBlockData) {
                DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
            };
        }

        $collectionsWebBlock = $this->getWebpageBlocksByType($webpage, 'collections-1');
        if (count($collectionsWebBlock) > 0) {
            foreach ($collectionsWebBlock as $webBlockData) {
                DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
            };
        }


        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'sub-departments-1');

        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'sub-departments-1', $department);
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'overview_aurora');

        if (count($countFamilyWebBlock) > 0) {
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
                    $command->line('D: '.$department->id.' Department description updated');
                    $department->update(['description' => $description]);
                }

                DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
            };
        }


        $productsWebBlock = $this->getWebpageBlocksByType($webpage, 'products-1');

        if (count($productsWebBlock) == 0) {
            $command->error('Webpage '.$webpage->code.' Products Web Block not found');
            $this->createWebBlock($webpage, 'products-1', $department);
        } elseif (count($productsWebBlock) > 1) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 Products Web Block found');
        }


        $productsWebBlock = $this->getWebpageBlocksByType($webpage, 'families-1');

        if (count($productsWebBlock) == 0) {
            $command->error('Webpage '.$webpage->code.' Families Web Block not found');
            $this->createWebBlock($webpage, 'families-1', $department);
        } elseif (count($productsWebBlock) > 1) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 Families Web Block found');
        }


        $webpage->refresh();
        UpdateWebpageContent::run($webpage);
        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";


        if ($webpage->is_dirty) {
            if (in_array($department->state, [
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


    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_departments_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('sub_type', 'department')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
        }
    }

}

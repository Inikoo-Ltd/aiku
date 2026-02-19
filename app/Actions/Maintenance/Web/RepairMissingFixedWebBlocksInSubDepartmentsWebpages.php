<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 10:10:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInSubDepartmentsWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        /** @var ProductCategory $model */
        $model = $webpage->model;
        if ($model->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $this->processSubDepartmentWebpages($webpage, $command);
        }
    }


    protected function processSubDepartmentWebpages(Webpage $webpage, Command $command): void
    {



        /** @var ProductCategory $subDepartment */
        $subDepartment = $webpage->model;

        $productsWebBlock = $this->getWebpageBlocksByType($webpage, 'families-1');

        if (count($productsWebBlock) == 0) {
            $command->error('Webpage '.$webpage->code.' Families Web Block not found');
            $this->createWebBlock($webpage, 'families-1');
        } elseif (count($productsWebBlock) > 1) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 Families Web Block found');
        }

        $productsWebBlock = $this->getWebpageBlocksByType($webpage, 'products-1');

        if (count($productsWebBlock) == 0) {
            $command->error('Webpage '.$webpage->code.' Products Web Block not found');
            $this->createWebBlock($webpage, 'products-1');
        } elseif (count($productsWebBlock) > 1) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 Products Web Block found');
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


        $countCollectionDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'sub-department-description-1');
        if (count($countCollectionDescriptionBlock) == 0) {
            $this->createWebBlock($webpage, 'sub-department-description-1');
        }

        $webpage->refresh();
        $this->setDescriptionWebBlockOnTop($webpage);
        $webpage->refresh();

        UpdateWebpageContent::run($webpage);
        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";

        if ($webpage->is_dirty) {
            $command->line('Webpage '.$webpage->code.' is dirty, publishing after upgrade');
            PublishWebpage::make()->action(
                $webpage,
                [
                    'comment' => 'publish after upgrade',
                ]
            );
        }


    }

    public function setDescriptionWebBlockOnTop(Webpage $webpage): void
    {
        $subDepartmentDescriptionWebBlock = $this->getWebpageBlocksByType($webpage, 'sub-department-description-1')->first()->model_has_web_blocks_id;
        $webBlocks = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();


        $runningPosition = 2;
        foreach ($webBlocks as $key => $position) {
            if ($key == $subDepartmentDescriptionWebBlock) {
                $webBlocks[$key] = 1;
            } else {
                $webBlocks[$key] = $runningPosition;
                $runningPosition++;
            }
        }


        foreach ($webBlocks as $key => $position) {
            DB::table('model_has_web_blocks')
                ->where('id', $key)
                ->update(['position' => $position]);
        }
        UpdateWebpageContent::run($webpage);
    }


    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_sub_departments_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('sub_type', 'sub_department')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
        }
    }

}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 09:47:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
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


        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'sub-departments-1');

        if (count($countFamilyWebBlock) == 0) {

            $this->createWebBlock($webpage, 'sub-departments-1', $department);
        }


        $webpage->refresh();
        //        foreach($webpage->webBlocks as $webblock) {
        //            print $webblock->webBlockType->code . "\n";
        //        }
        //        exit;
    }


    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_department_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('model_type', 'ProductCategory')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            $this->handle($webpage, $command);
        }
    }

}

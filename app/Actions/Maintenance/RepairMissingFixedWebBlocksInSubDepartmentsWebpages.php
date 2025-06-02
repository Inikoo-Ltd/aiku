<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 10:10:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Traits\WithActionUpdate;
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

        $productsWebBlock = $this->getWebpageBlocksByType($webpage, 'products-1');

        if ($productsWebBlock == 0) {
            $command->error('Webpage '.$webpage->code.' Products Web Block not found');
            $this->createWebBlock($webpage, 'products-1', $subDepartment);
        } elseif ($productsWebBlock > 1) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 Products Web Block found');
        } else {
            $command->info('Webpage '.$webpage->code.' Products Web Block found');
        }


        $productsWebBlock = $this->getWebpageBlocksByType($webpage, 'families-1');

        if ($productsWebBlock == 0) {
            $command->error('Webpage '.$webpage->code.' Families Web Block not found');
            $this->createWebBlock($webpage, 'families-1', $subDepartment);
        } elseif ($productsWebBlock > 1) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 Families Web Block found');
        } else {
            $command->info('Webpage '.$webpage->code.' Families Web Block found');
        }

    }


    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_sub_departments_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('model_type', 'ProductCategory')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            $this->handle($webpage, $command);
        }
    }

}

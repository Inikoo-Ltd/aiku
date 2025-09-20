<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 16:32:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterFamilyDepartment
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterFamily): void
    {

        if ($masterFamily->masterSubDepartment) {
            if ($masterFamily->masterSubDepartment->type != MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                dd($masterFamily->masterSubDepartment);
                dd('Error master sub department is no sub department');
            }

            UpdateMasterProductCategory::make()->action(
                $masterFamily,
                [
                    'master_department_id' => $masterFamily->masterSubDepartment->master_department_id
                ]
            );

        }

        //        if($masterFamily->masterDepartment){
        //            if($masterFamily->masterDepartment->type != MasterProductCategoryTypeEnum::DEPARTMENT){
        //                dd($masterFamily->masterDepartment);
        //                dd('Error master sub department is no sub department');
        //            }
        //        }
    }



    public function getCommandSignature(): string
    {
        return 'repair:master_family_department';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        MasterProductCategory::where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->orderBy('id')
            ->chunkById(500, function ($families) {
                foreach ($families as $masterFamily) {
                    $this->handle($masterFamily);
                }
            }, 'id');
        return 0;
    }


}

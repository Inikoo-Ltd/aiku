<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 16:32:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairFamilyDepartment
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $family): void
    {
        if ($family->subDepartment) {
            if ($family->subDepartment->type != ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                dd($family->subDepartment);
            }

            UpdateProductCategory::make()->action(
                $family,
                [
                    'department_id' => $family->subDepartment->department_id
                ]
            );
        }
        $family->refresh();
        if ($family->department) {
            if ($family->department->type != ProductCategoryTypeEnum::DEPARTMENT) {
                dd($family->department);
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'repair:family_department';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        ProductCategory::where('type', ProductCategoryTypeEnum::FAMILY)
            ->orderBy('id')
            ->chunkById(500, function ($families) {
                foreach ($families as $family) {
                    $this->handle($family);
                }
            }, 'id');

        return 0;
    }


}

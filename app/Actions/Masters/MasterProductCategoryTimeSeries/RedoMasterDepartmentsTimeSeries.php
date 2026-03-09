<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:34:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class RedoMasterDepartmentsTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand, WithRedoMasterProductCategoryTimeSeries {
        WithRedoMasterProductCategoryTimeSeries::asCommand insteadof WithHydrateCommand;
        WithRedoMasterProductCategoryTimeSeries::asJob insteadof WithHydrateCommand;
    }

    protected ?MasterProductCategoryTypeEnum $categoryType;

    public string $jobQueue         = 'default-long';
    public string $commandSignature = 'master-departments:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model        = MasterProductCategory::class;
        $this->categoryType = MasterProductCategoryTypeEnum::DEPARTMENT;
    }
}

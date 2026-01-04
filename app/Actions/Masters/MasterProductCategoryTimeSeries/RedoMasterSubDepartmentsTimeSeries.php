<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:34:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Masters\MasterProductCategory;

class RedoMasterSubDepartmentsTimeSeries
{
    use WithHydrateCommand;
    use WithRedoMasterProductCategoryTimeSeries {
        WithRedoMasterProductCategoryTimeSeries::asCommand insteadof WithHydrateCommand;
    }

    public string $commandSignature = 'master_sub_departments:redo_time_series {organisations?*} {--S|shop= shop slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model       = MasterProductCategory::class;
        $this->restriction = 'sub_department';
    }
}

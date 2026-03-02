<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:34:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;

class RedoMasterSubDepartmentsTimeSeries
{
    use AsAction;
    use WithRedoMasterProductCategoryTimeSeries;

    protected ?MasterProductCategoryTypeEnum $restriction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'master-sub-departments:redo_time_series {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->restriction = MasterProductCategoryTypeEnum::SUB_DEPARTMENT;
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 May 2024 14:33:33 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\HumanResources\Timesheet;

class HydrateTimesheets
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:timesheets {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Timesheet::class;
    }

    public function handle(Timesheet $timesheet): void
    {
        TimesheetHydrateTimeTrackers::run($timesheet);
    }

}

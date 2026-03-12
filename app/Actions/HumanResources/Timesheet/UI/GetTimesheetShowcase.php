<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:57:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\UI;

use App\Models\HumanResources\Timesheet;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTimesheetShowcase
{
    use AsAction;

    public function handle(Timesheet $timesheet): array
    {
        $workStartAt = $timesheet->start_at;
        $workEndAt = $timesheet->end_at;

        if ($timesheet->organisation?->code === 'SK') {
            $workStartAt = $workStartAt?->copy()->subHour();
            $workEndAt = $workEndAt?->copy()->subHour();
        }

        return [
            'work_start_at'      => $workStartAt,
            'work_end_at'        => $workEndAt,
            'work_duration'      => $timesheet->working_duration,
            'breaks_duration'    => $timesheet->breaks_duration,
            'total_duration'     => $timesheet->total_duration,
            'overtime'           => $timesheet->overtime,
            'about'              => $timesheet->about
        ];
    }
}

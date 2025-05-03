<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 16:02:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\Hydrators;

use App\Actions\Traits\WithJobPositionableShare;
use App\Actions\Traits\WithNormalise;
use App\Models\HumanResources\Employee;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class EmployeeHydrateJobPositionsShare implements ShouldBeUnique
{
    use AsAction;
    use WithNormalise;
    use WithJobPositionableShare;

    public function getJobUniqueId(Employee $employee): string
    {
        return $employee->id;
    }

    public function handle(Employee $employee): void
    {

        $employee->stats()->update(
            [
                'number_job_positions' => $employee->jobPositions()->count(),
            ]
        );

        foreach ($this->getJobPositionShares($employee) as $job_position_id => $share) {
            $employee->jobPositions()->updateExistingPivot($job_position_id, [
                'share' => $share,
            ]);
        }
    }


}

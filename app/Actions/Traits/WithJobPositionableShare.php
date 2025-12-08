<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 11:20:24 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Guest;

trait WithJobPositionableShare
{
    public function getJobPositionShares(Employee|Guest $jobPositionable): array
    {
        $jobPositions = $this->normalise(
            collect(
                $jobPositionable->jobPositions()->whereNotNull('share')->pluck('share', 'job_position_id')
            )
        );

        $jobPositionsNoShare = $jobPositionable->jobPositions()->whereNull('share')->pluck('job_position_id');

        $numberJobPositionsNoShare = count($jobPositionsNoShare);
        $numberJobPositions = count($jobPositions);

        if ($numberJobPositionsNoShare == 0) {
            return $jobPositions;
        }

        $numberSlots = $numberJobPositionsNoShare + $numberJobPositions;

        $shares = [];
        foreach ($jobPositionsNoShare as $id) {
            $shares[$id] = 1 / $numberSlots;
        }
        foreach ($jobPositions as $id => $share) {
            $shares[$id] = $share * $numberJobPositions / $numberSlots;
        }

        return $this->normalise(collect($shares));
    }
}

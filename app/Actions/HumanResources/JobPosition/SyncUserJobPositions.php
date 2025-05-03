<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Aug 2024 11:33:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\HumanResources\JobPosition\Hydrators\JobPositionHydrateGuests;
use App\Actions\SysAdmin\User\SyncRolesFromJobPositions;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class SyncUserJobPositions
{
    use AsObject;

    public function handle(User $user, array $jobPositions): void
    {
        $jobPositionsIds = array_keys($jobPositions);

        $currentJobPositions = $user->pseudoJobPositions()->pluck('job_positions.id')->all();
        $newJobPositionsIds  = array_diff($jobPositionsIds, $currentJobPositions);
        $removeJobPositions  = array_diff($currentJobPositions, $jobPositionsIds);

        $user->pseudoJobPositions()->detach($removeJobPositions);




        foreach ($newJobPositionsIds as $jobPositionId) {

            $jobPosition = JobPosition::find($jobPositionId);

            $pseudoJobPositionsData = [
                'group_id'        => $user->group_id,
                'scopes'          => $jobPositions[$jobPositionId],
                'organisation_id' => $jobPosition->organisation_id
            ];


            $user->pseudoJobPositions()->attach(
                [
                    $jobPositionId => $pseudoJobPositionsData
                ],
            );
        }
        $user->refresh();


        if (count($newJobPositionsIds) || count($removeJobPositions)) {

            SyncRolesFromJobPositions::run($user);


            foreach ($removeJobPositions as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateGuests::dispatch($jobPosition);
            }

            foreach ($newJobPositionsIds as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateGuests::dispatch($jobPosition);
            }
        }
    }
}

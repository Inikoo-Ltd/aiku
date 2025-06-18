<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jan 2025 03:17:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\HumanResources\JobPosition\Hydrators\JobPositionHydrateEmployees;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncUserPseudoOrganisationJobPositions
{
    use AsAction;

    public function handle(User $user, Organisation $organisation, array $jobPositions): User
    {
        $jobPositionsIds = array_keys($jobPositions);

        $currentJobPositions = $user->pseudoJobPositions()->where('job_positions.organisation_id', $organisation->id)->pluck('job_positions.id')->all();

        $newJobPositionsIds   = array_diff($jobPositionsIds, $currentJobPositions);
        $removeJobPositions   = array_diff($currentJobPositions, $jobPositionsIds);
        $jobPositionsToUpdate = array_intersect($jobPositionsIds, $currentJobPositions);

        $user->pseudoJobPositions()->detach($removeJobPositions);
        foreach ($newJobPositionsIds as $jobPositionId) {
            $user->pseudoJobPositions()->attach(
                [
                    $jobPositionId => [
                        'group_id'        => $user->group_id,
                        'organisation_id' => $organisation->id,
                        'scopes'          => $jobPositions[$jobPositionId]
                    ]
                ],
            );
        }

        foreach ($jobPositionsToUpdate as $jobPositionId) {
            $user->pseudoJobPositions()->updateExistingPivot(
                $jobPositionId,
                [
                    'scopes' => $jobPositions[$jobPositionId]
                ]
            );
        }

        SyncRolesFromJobPositions::run($user);
        if (count($newJobPositionsIds) || count($removeJobPositions)) {
            foreach ($removeJobPositions as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateEmployees::dispatch($jobPosition);
            }

            foreach ($newJobPositionsIds as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateEmployees::dispatch($jobPosition);
            }
        }

        return $user;
    }
}

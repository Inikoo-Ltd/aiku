<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;

/**
 * Agent managers run their agent without being its Organisation Administrator, and may neither give that
 * position to anyone nor take it away (HELP-3457). Whoever already administers the organisation still can.
 */
trait WithWithheldJobPositions
{
    /**
     * @return array<int>
     */
    protected function withheldJobPositionIds(Organisation $organisation, ?User $editor): array
    {
        if (!$editor || $organisation->type !== OrganisationTypeEnum::AGENT || $editor->authTo('org-admin.'.$organisation->id)) {
            return [];
        }

        return $organisation->jobPositions()->where('code', 'org-admin')->pluck('id')->all();
    }

    /**
     * @return Collection<int, JobPosition>
     */
    protected function assignableJobPositions(Organisation $organisation, ?User $editor): Collection
    {
        return $organisation->jobPositions()
            ->whereNotIn('id', $this->withheldJobPositionIds($organisation, $editor))
            ->get();
    }

    /**
     * @param  array<int, array<string, array<int>>>  $jobPositions  scopes keyed by job position id
     *
     * @return array<int, array<string, array<int>>>
     */
    protected function keepWithheldJobPositionsAsTheyWere(array $jobPositions, Organisation $organisation, ?User $editor, ?Employee $employee = null): array
    {
        foreach ($this->withheldJobPositionIds($organisation, $editor) as $jobPositionId) {
            unset($jobPositions[$jobPositionId]);

            $heldJobPosition = $employee?->jobPositions()->where('job_positions.id', $jobPositionId)->first();
            if ($heldJobPosition) {
                $scopes                       = $heldJobPosition->pivot->scopes;
                $jobPositions[$jobPositionId] = is_string($scopes) ? json_decode($scopes, true) : ($scopes ?? []);
            }
        }

        return $jobPositions;
    }
}

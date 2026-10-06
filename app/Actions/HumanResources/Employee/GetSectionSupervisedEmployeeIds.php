<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\HumanResources\Employee;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Section supervisors (warehouse, goods in, goods out, production, fulfilment) can see, read only,
 * the HR records of every employee in the organisation who holds a job position in the same section.
 * A section is the job position code prefix, e.g. a `wah-m` supervisor sees every employee with a `wah-*` position.
 */
class GetSectionSupervisedEmployeeIds
{
    use AsObject;

    public const array SECTION_SUPERVISOR_CODES = [
        'wah-m'  => 'wah',
        'gi-m'   => 'gi',
        'dist-m' => 'dist',
        'prod-m' => 'prod',
        'ful-m'  => 'ful',
    ];

    /**
     * @return array<int>|null null when the user has full HR view, otherwise the ids of the employees the user may see (empty when none)
     */
    public function handle(User $user, Organisation $organisation): ?array
    {
        if ($this->hasFullHumanResourcesView($user, $organisation)) {
            return null;
        }

        $sectionPrefixes = $this->getSupervisedSectionPrefixes($user, $organisation);

        if (empty($sectionPrefixes)) {
            return [];
        }

        return Employee::where('organisation_id', $organisation->id)
            ->whereHas('jobPositions', function ($query) use ($organisation, $sectionPrefixes) {
                $query->where('job_positions.organisation_id', $organisation->id)
                    ->where(function ($query) use ($sectionPrefixes) {
                        foreach ($sectionPrefixes as $sectionPrefix) {
                            $query->orWhere('job_positions.code', 'like', $sectionPrefix.'-%');
                        }
                    });
            })
            ->pluck('id')
            ->all();
    }

    public function hasFullHumanResourcesView(User $user, Organisation $organisation): bool
    {
        return $user->authTo([
            "human-resources.$organisation->id.view",
            "org-supervisor.$organisation->id.human-resources",
        ]);
    }

    /**
     * @return array<string>
     */
    public function getSupervisedSectionPrefixes(User $user, Organisation $organisation): array
    {
        if (!$user->status) {
            return [];
        }

        $jobPositionCodes = $user->employees()
            ->wherePivot('status', true)
            ->where('employees.organisation_id', $organisation->id)
            ->where('employees.state', '!=', EmployeeStateEnum::LEFT)
            ->with('jobPositions:job_positions.id,job_positions.code')
            ->get()
            ->flatMap(fn (Employee $employee) => $employee->jobPositions->pluck('code'))
            ->merge($user->pseudoJobPositions()->where('job_positions.organisation_id', $organisation->id)->pluck('job_positions.code'));

        return $jobPositionCodes
            ->map(fn (string $code) => self::SECTION_SUPERVISOR_CODES[$code] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Traits\Authorisations;

use App\Actions\HumanResources\Employee\GetSectionSupervisedEmployeeIds;
use Lorisleiva\Actions\ActionRequest;

/**
 * HR pages a section supervisor may open read only, limited to the employees of their section.
 * Full HR users are unaffected: $sectionEmployeeIds stays null and nothing is filtered.
 */
trait WithHumanResourcesSectionAuthorisation
{
    public const array SECTION_SUPERVISOR_ROUTES = [
        'grp.org.hr.dashboard',
        'grp.org.hr.employees.index',
        'grp.org.hr.employees.show',
        'grp.org.hr.employees.show.timesheets.index',
        'grp.org.hr.employees.show.timesheets.show',
        'grp.org.hr.timesheets.index',
        'grp.org.hr.timesheets.show',
        'grp.org.hr.clockings.index',
        'grp.org.hr.clockings.show',
        'grp.org.hr.leaves.index',
        'grp.org.hr.overtime.index',
    ];

    /** @var array<int>|null */
    public ?array $sectionEmployeeIds = null;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (str_starts_with($request->route()->getName(), 'grp.overview.hr.')) {
            return $request->user()->authTo("group-overview");
        }

        $sectionEmployeeIds = GetSectionSupervisedEmployeeIds::run($request->user(), $this->organisation);

        if ($sectionEmployeeIds === null) {
            $this->canEdit = $request->user()->authTo(["human-resources.{$this->organisation->id}.edit", "org-supervisor.{$this->organisation->id}.human-resources"]);

            return true;
        }

        if (empty($sectionEmployeeIds) || !in_array($request->route()->getName(), self::SECTION_SUPERVISOR_ROUTES, true)) {
            return false;
        }

        $this->canEdit            = false;
        $this->sectionEmployeeIds = $sectionEmployeeIds;

        return true;
    }

    public function isRestrictedToSection(): bool
    {
        return $this->sectionEmployeeIds !== null;
    }

    public function canSeeEmployee(?int $employeeId): bool
    {
        return !$this->isRestrictedToSection() || in_array($employeeId, $this->sectionEmployeeIds, true);
    }

    public function restrictToSectionEmployees($query, string $employeeIdColumn): void
    {
        if ($this->isRestrictedToSection()) {
            $query->whereIn($employeeIdColumn, $this->sectionEmployeeIds);
        }
    }
}

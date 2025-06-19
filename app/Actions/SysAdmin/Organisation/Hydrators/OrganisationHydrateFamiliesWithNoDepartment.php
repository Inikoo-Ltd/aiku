<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateFamiliesWithNoDepartment implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_families_no_department' => $organisation->getFamilies()->whereNull('department_id')->count(),
        ];

        $organisation->catalogueStats()->update($stats);
    }


}

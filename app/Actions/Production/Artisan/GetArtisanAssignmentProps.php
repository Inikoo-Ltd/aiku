<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactFamily;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtisanAssignmentProps
{
    use AsObject;

    /** @return array{current: array<int, array{id: int, name: string}>, options: array<int, array{id: int, name: string}>, attach_route: array|null, detach_route: array|null} */
    public function handle(Artefact|ArtefactFamily $model, bool $canEdit): array
    {
        $routeKey = $model instanceof Artefact ? 'artefact' : 'artefact_family';

        return [
            'current' => $model->artisans->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name])->values()->all(),
            'options' => $canEdit ? Employee::where('organisation_id', $model->organisation_id)
                ->where('state', EmployeeStateEnum::WORKING)
                ->orderBy('contact_name')
                ->get(['id', 'contact_name'])
                ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name])
                ->all() : [],
            'attach_route' => $canEdit ? ['name' => "grp.models.{$routeKey}.artisans.attach", 'parameters' => [$model->id]] : null,
            'detach_route' => $canEdit ? ['name' => "grp.models.{$routeKey}.artisans.detach", 'parameters' => [$model->id]] : null,
        ];
    }
}

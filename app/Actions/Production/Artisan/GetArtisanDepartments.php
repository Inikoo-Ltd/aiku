<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Models\Production\Production;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtisanDepartments
{
    use AsObject;

    /**
     * The departments made in the artisans' work areas.
     *
     * @return Collection<int, array<int, array{id: int, name: string}>> keyed by employee id
     */
    public function handle(Production $production): Collection
    {
        return DB::table('artisan_assignments')
            ->join('artefact_departments', 'artefact_departments.work_area_id', 'artisan_assignments.artisanable_id')
            ->where('artisan_assignments.artisanable_type', 'WorkArea')
            ->where('artefact_departments.production_id', $production->id)
            ->whereNull('artefact_departments.deleted_at')
            ->orderBy('artefact_departments.name')
            ->get(['artisan_assignments.employee_id', 'artefact_departments.id', 'artefact_departments.name'])
            ->groupBy('employee_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])->values()->all());
    }

    /**
     * The artisans of the products' departments come first, each group keeping its order.
     *
     * @param  array<int, array{id: int}>  $artisanOptions
     * @param  array<int, int|null>  $departmentIds
     */
    public function departmentFirst(Production $production, array $artisanOptions, array $departmentIds): array
    {
        $departmentIds = array_filter($departmentIds);
        if (!$departmentIds) {
            return $artisanOptions;
        }

        $artisanDepartments = $this->handle($production);
        $inDepartment       = fn (array $option) => (bool) array_intersect($departmentIds, array_column($artisanDepartments->get($option['id'], []), 'id'));

        return [
            ...array_values(array_filter($artisanOptions, $inDepartment)),
            ...array_values(array_filter($artisanOptions, fn (array $option) => !$inDepartment($option))),
        ];
    }

    /**
     * @return Collection<int, array<int, int>> keyed by employee id
     */
    public function departmentIdsByArtisan(Production $production): Collection
    {
        return $this->handle($production)->map(fn (array $departments) => array_column($departments, 'id'));
    }
}

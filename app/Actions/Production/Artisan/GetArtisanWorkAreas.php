<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Models\Production\Production;
use App\Models\Production\WorkArea;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtisanWorkAreas
{
    use AsObject;

    /**
     * @return Collection<int, array<int, array{id: int, name: string}>> keyed by employee id
     */
    public function handle(Production $production): Collection
    {
        return DB::table('artisan_assignments')
            ->join('work_areas', 'work_areas.id', 'artisan_assignments.artisanable_id')
            ->where('artisan_assignments.artisanable_type', 'WorkArea')
            ->where('work_areas.production_id', $production->id)
            ->orderBy('work_areas.name')
            ->get(['artisan_assignments.employee_id', 'work_areas.id', 'work_areas.name'])
            ->groupBy('employee_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])->values()->all());
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function options(Production $production): array
    {
        return $production->workAreas()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (WorkArea $workArea) => ['id' => $workArea->id, 'name' => $workArea->name])
            ->all();
    }

    /**
     * @return array<int, int> employee ids in the work area
     */
    public function employeeIds(Production $production, int $workAreaId): array
    {
        return $this->handle($production)
            ->filter(fn (array $workAreas) => in_array($workAreaId, array_column($workAreas, 'id')))
            ->keys()
            ->all();
    }
}

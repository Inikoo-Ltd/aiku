<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\WorkArea;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\Production;
use App\Models\Production\WorkArea;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Renames a work area and sets the product departments made there. A department is made in one area, so picking it here takes it out of any other.
 */
class UpdateWorkArea extends OrgAction
{
    use WithWorkAreaAuthorisation;

    private WorkArea $workArea;

    public function handle(WorkArea $workArea, array $modelData): WorkArea
    {
        DB::transaction(function () use ($workArea, $modelData) {
            if (Arr::has($modelData, 'name')) {
                $workArea->update(['name' => $modelData['name']]);
            }

            if (Arr::has($modelData, 'artefact_department_ids')) {
                $departmentIds = array_map('intval', $modelData['artefact_department_ids']);
                ArtefactDepartment::where('work_area_id', $workArea->id)->whereNotIn('id', $departmentIds)->update(['work_area_id' => null]);
                ArtefactDepartment::where('production_id', $workArea->production_id)->whereIn('id', $departmentIds)->update(['work_area_id' => $workArea->id]);
            }
        });

        return $workArea;
    }

    public function rules(): array
    {
        return [
            'name'                      => ['sometimes', 'required', 'string', 'max:255', new IUnique(table: 'work_areas', extraConditions: [
                ['column' => 'production_id', 'value' => $this->production->id],
                ['column' => 'id', 'value' => $this->workArea->id, 'operator' => '!='],
            ])],
            'artefact_department_ids'   => ['sometimes', 'array'],
            'artefact_department_ids.*' => ['integer', Rule::exists('artefact_departments', 'id')->where('production_id', $this->production->id)->whereNull('deleted_at')],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(WorkArea $workArea, array $modelData): WorkArea
    {
        $this->asAction = true;
        $this->workArea = $workArea;
        $this->initialisationFromProduction($workArea->production, $modelData);

        return $this->handle($workArea, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, WorkArea $workArea, ActionRequest $request): WorkArea
    {
        abort_if($workArea->production_id != $production->id, 404);
        $this->workArea = $workArea;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($workArea, $this->validatedData);
    }
}

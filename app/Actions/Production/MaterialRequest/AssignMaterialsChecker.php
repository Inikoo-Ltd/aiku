<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\MaterialRequest;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\UI\IndexJobOrderPlan;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Models\Production\Production;
use App\Models\Production\ProductionMaterialRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class AssignMaterialsChecker extends OrgAction
{
    /**
     * Gives the morning materials check of a day to an operator, who then sees it on the floor.
     */
    public function handle(Production $production, string $date, ?int $employeeId): ProductionMaterialRequest
    {
        if (!$production->factory_location_id) {
            throw ValidationException::withMessages(['production' => __('This production has no factory location')]);
        }

        $request = MaterialsCheckAuthorisation::checkingRequest($production, $date) ?? ProductionMaterialRequest::create([
            'group_id'        => $production->group_id,
            'organisation_id' => $production->organisation_id,
            'production_id'   => $production->id,
            'warehouse_id'    => $production->factoryLocation->warehouse_id,
            'plan_date'       => $date,
            'state'           => ProductionMaterialRequestStateEnum::CHECKING,
        ]);

        $request->update(['checker_employee_id' => $employeeId]);

        return $request;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return MaterialsCheckAuthorisation::canManageMaterialsCheck($request->user(), $this->production);
    }

    public function rules(): array
    {
        return [
            'date'        => ['required', 'date_format:Y-m-d'],
            'employee_id' => ['present', 'nullable', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function action(Production $production, string $date, ?int $employeeId): ProductionMaterialRequest
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, ['date' => $date, 'employee_id' => $employeeId]);

        return $this->handle($production, $this->validatedData['date'], $this->validatedData['employee_id']);
    }

    public function asController(Production $production, ActionRequest $request): ProductionMaterialRequest
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, IndexJobOrderPlan::parseDate($this->validatedData['date'], $production), $this->validatedData['employee_id']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}

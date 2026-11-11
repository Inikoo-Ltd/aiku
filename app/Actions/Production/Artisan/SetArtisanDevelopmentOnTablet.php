<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 19:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Models\HumanResources\Employee;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SetArtisanDevelopmentOnTablet extends OrgAction
{
    /**
     * Development pays more than the base rate, so the tablet offers it only to the artisans the Production Manager
     * switches it on for (HELP-4648), until the day given if any. The employee's audit trail keeps who changed it and when.
     *
     * @param  array{development_on_tablet: bool, development_on_tablet_until?: string|null}  $modelData
     */
    public function handle(Employee $employee, array $modelData): Employee
    {
        $employee->update([
            'development_on_tablet'       => $modelData['development_on_tablet'],
            'development_on_tablet_until' => $modelData['development_on_tablet'] ? ($modelData['development_on_tablet_until'] ?? null) : null,
        ]);

        return $employee;
    }

    public function rules(): array
    {
        return [
            'development_on_tablet'       => ['required', 'boolean'],
            'development_on_tablet_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:'.Employee::developmentDay($this->organisation)],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'development_on_tablet_until.after_or_equal' => __('The last day cannot be in the past'),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('org-supervisor.'.$this->organisation->id);
    }

    public function action(Production $production, Employee $employee, array $modelData): Employee
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($employee, $this->validatedData);
    }

    public function asController(Production $production, Employee $employee, ActionRequest $request): Employee
    {
        abort_unless(HydrateManufactureArtisanDays::isArtisan($production, $employee), 404);
        $this->initialisationFromProduction($production, $request);

        return $this->handle($employee, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}

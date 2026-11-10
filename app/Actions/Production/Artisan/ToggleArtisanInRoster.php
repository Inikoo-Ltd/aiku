<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ToggleArtisanInRoster extends OrgAction
{
    // ponytail: an hour on the tablet in locked weeks makes somebody an artisan; a dated hidden list if this is too blunt
    public const float ARTISAN_HOURS = 1.0;

    public function handle(Production $production, Employee $employee, bool $hidden): Production
    {
        $hiddenIds = collect(Arr::get($production->data, 'hidden_artisan_ids', []))
            ->reject(fn (int $id) => $id === $employee->id);
        if ($hidden) {
            $hiddenIds->push($employee->id);
        }

        $production->update(['data' => array_merge($production->data ?? [], ['hidden_artisan_ids' => $hiddenIds->values()->all()])]);

        return $production;
    }

    /**
     * Hiding somebody takes their tablet time and clocked hours out of factory payroll for every week, and showing them
     * again puts them back. For somebody with real tablet time in a week already locked that would change a paid week
     * with no record, so only the accountant can do it.
     */
    public static function changesLockedWeeks(Production $production, Employee $employee): bool
    {
        return (float) ManufactureTaskSession::where('production_id', $production->id)
            ->where('employee_id', $employee->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->where('ended_at', '<', ManufacturePayrollAdjustment::lockedBefore($production))
            ->sum('hours') >= self::ARTISAN_HOURS;
    }

    private function refuseForPaidArtisan(Production $production, Employee $employee, ActionRequest $request): void
    {
        if (self::changesLockedWeeks($production, $employee) && !ManufacturePayrollAdjustment::canChangeLockedWeek($request->user(), $production)) {
            throw ValidationException::withMessages([
                'artisan' => __(':name has worked on the floor in weeks already locked for payroll, so only the accountant can change whether they are an artisan', ['name' => $employee->contact_name]),
            ]);
        }
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(Production $production, Employee $employee, bool $hidden): Production
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, []);

        return $this->handle($production, $employee, $hidden);
    }

    public function hide(Organisation $organisation, Production $production, Employee $employee, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);
        $this->refuseForPaidArtisan($production, $employee, $request);

        return $this->handle($production, $employee, true);
    }

    public function show(Organisation $organisation, Production $production, Employee $employee, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);
        $this->refuseForPaidArtisan($production, $employee, $request);

        return $this->handle($production, $employee, false);
    }
}

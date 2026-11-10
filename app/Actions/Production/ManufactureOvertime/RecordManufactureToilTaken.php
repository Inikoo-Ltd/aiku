<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureOvertime;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufactureOvertime;
use App\Models\Production\ManufactureToilTaken;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A manager records time off in lieu an artisan took; it comes off their TOIL balance and never takes it below zero.
 */
class RecordManufactureToilTaken extends OrgAction
{
    public function handle(Production $production, Employee $employee, User $user, array $modelData): ManufactureToilTaken
    {
        $minutes = (int) round((float) $modelData['hours'] * 60);
        $balance = ManufactureOvertime::toilBalances($production, now()->toDateString(), [$employee->id])[$employee->id] ?? 0;
        if ($minutes > $balance) {
            throw ValidationException::withMessages([
                'hours' => __('Only :hours h of time off in lieu is owed', ['hours' => number_format(max(0, $balance) / 60, 2)]),
            ]);
        }

        return ManufactureToilTaken::create([
            'group_id'            => $production->group_id,
            'organisation_id'     => $production->organisation_id,
            'production_id'       => $production->id,
            'employee_id'         => $employee->id,
            'date'                => $modelData['date'],
            'minutes'             => $minutes,
            'note'                => $modelData['note'] ?? null,
            'recorded_by_user_id' => $user->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
            'date'        => ['required', 'date_format:Y-m-d'],
            'hours'       => ['required', 'numeric', 'min:0.01', 'max:100'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function action(Production $production, Employee $employee, User $user, array $modelData): ManufactureToilTaken
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData + ['employee_id' => $employee->id]);

        return $this->handle($production, $employee, $user, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): ManufactureToilTaken
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, Employee::findOrFail($this->validatedData['employee_id']), $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 12:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseTeam;

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\Inventory\WarehouseTeam\UI\ShowWarehouseTeam;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWarehouseTeamAuthorisation;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreWarehouseTeamClocking extends OrgAction
{
    use WithWarehouseTeamAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, Employee $employee, User $manager, array $modelData): Clocking
    {
        if (!ShowWarehouseTeam::teamQuery($warehouse)->whereKey($employee->id)->exists()) {
            throw ValidationException::withMessages(['employee' => __('This employee is not in the warehouse team.')]);
        }

        $workplace = $employee->workplaces()->first() ?? $employee->organisation->workplaces()->first();
        if (!$workplace) {
            throw ValidationException::withMessages(['employee' => __('The organisation has no workplace to clock in.')]);
        }

        return StoreClocking::make()->action($manager, $workplace, $employee, [
            'clocked_at' => Carbon::parse($modelData['clocked_at'])->utc(),
            'notes'      => $modelData['notes'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'clocked_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, Employee $employee, ActionRequest $request): Clocking
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $employee, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Clocking added.'),
        ]);
    }
}

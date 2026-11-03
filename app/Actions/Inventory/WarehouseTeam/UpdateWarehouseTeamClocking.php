<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 13:30:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseTeam;

use App\Actions\HumanResources\Clocking\UpdateClockingNotes;
use App\Actions\OrgAction;
use App\Actions\Production\ManufacturePayrollAdjustment\ChangeArtisanClocking;
use App\Actions\Traits\Authorisations\WithWarehouseTeamAuthorisation;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateWarehouseTeamClocking extends OrgAction
{
    use WithWarehouseTeamAuthorisation;

    public function handle(Warehouse $warehouse, Clocking $clocking, array $modelData): Clocking
    {
        self::ensureTeamClocking($warehouse, $clocking);

        return UpdateClockingNotes::run($clocking, $modelData['notes'] ?? null, $modelData['clocked_at']);
    }

    public function rules(): array
    {
        return [
            'clocked_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'notes'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function asController(Warehouse $warehouse, Clocking $clocking, ActionRequest $request): Clocking
    {
        $this->initialisationFromWarehouse($warehouse, $request);
        $update = fn () => $this->handle($warehouse, $clocking, $this->validatedData);

        if (!$clocking->subject instanceof Employee || !$clocking->timesheet) {
            return $update();
        }

        return ChangeArtisanClocking::run($clocking->subject, $clocking->timesheet->date->copy()->min(Carbon::parse($this->validatedData['clocked_at'])), $clocking->timesheet, ['working_duration', 'start_at', 'end_at'], null, null, $update);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}

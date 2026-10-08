<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 13:30:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseTeam;

use App\Actions\HumanResources\Clocking\UpdateClockingNotes;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWarehouseTeamAuthorisation;
use App\Models\HumanResources\Clocking;
use App\Models\Inventory\Warehouse;
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

        return $this->handle($warehouse, $clocking, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}

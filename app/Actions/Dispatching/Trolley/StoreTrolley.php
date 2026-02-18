<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:44:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateTrolleys;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTrolleys;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateTrolleys;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreTrolley extends OrgAction
{
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, array $modelData): Trolley
    {
        $modelData['group_id']        = $warehouse->group_id;
        $modelData['organisation_id'] = $warehouse->organisation_id;
        $modelData['warehouse_id']    = $warehouse->id;

        $trolley = Trolley::create($modelData);

        WarehouseHydrateTrolleys::dispatch($trolley->warehouse);
        OrganisationHydrateTrolleys::dispatch($trolley->organisation);
        GroupHydrateTrolleys::dispatch($trolley->group);

        return $trolley;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'trolleys',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->warehouse->id],
                    ]
                ),
            ],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): Trolley
    {
        $this->warehouse = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(Trolley $trolley): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.trolleys.show', [
            $trolley->organisation->slug,
            $trolley->warehouse->slug,
            $trolley->slug,
        ]);
    }
}

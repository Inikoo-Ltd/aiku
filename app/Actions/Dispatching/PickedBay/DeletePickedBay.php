<?php

/*
 * author Louis Perez
 * created on 23-04-2026-16h-29m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\PickedBay;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickedBays;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePickedBays;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePickedBays;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\PickedBay;
use Illuminate\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeletePickedBay extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    private PickedBay $pickedBay;

    public function handle(PickedBay $pickedBay): PickedBay
    {
        $pickedBay->delete();

        WarehouseHydratePickedBays::dispatch($this->warehouse);
        OrganisationHydratePickedBays::dispatch($this->organisation);
        GroupHydratePickedBays::dispatch($this->group);

        return $pickedBay;
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->pickedBay->deliveryNotes()->exists()) {
            $validator->errors()->add('pickedBay', 'This picking bay is still used on several delivery notes');
        }
    }

    public function asController(PickedBay $pickedBay, ActionRequest $request): PickedBay
    {
        $this->pickedBay = $pickedBay;
        $this->initialisationFromWarehouse($pickedBay->warehouse, $request);

        return $this->handle($pickedBay);
    }

    public function htmlResponse(PickedBay $pickedBay): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.picked_bays.index', [
            $pickedBay->organisation->slug,
            $pickedBay->warehouse->slug,
        ]);
    }
}

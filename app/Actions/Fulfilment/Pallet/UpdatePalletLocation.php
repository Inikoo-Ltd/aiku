<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class UpdatePalletLocation extends OrgAction
{
    use WithActionUpdate;


    private Pallet $pallet;
    /**
     * @var array|\ArrayAccess|mixed
     */
    private mixed $scope;

    public function handle(Location $location, Pallet $pallet): Pallet
    {
        return $this->update($pallet, ['location_id' => $location->id]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scope instanceof Warehouse) {
            $this->canEdit = $request->user()->hasPermissionTo("locations.{$this->scope->id}.edit");

            return  $request->user()->hasPermissionTo("locations.{$this->scope->id}.edit");
        }

        $this->canEdit = $request->user()->hasPermissionTo("fulfilment.{$this->fulfilment->id}.edit");

        return $request->user()->hasPermissionTo("fulfilment.{$this->fulfilment->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Location $location, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->scope  = $pallet->fulfilment;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($location, $pallet);
    }

    public function action(Location $location, Pallet $pallet): Pallet
    {
        $this->asAction = true;
        $this->pallet   = $pallet;
        $this->initialisationFromFulfilment($pallet->fulfilment, []);

        return $this->handle($location, $pallet);
    }

    public function inWarehouse(Warehouse $warehouse, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->scope  = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        $location = Location::where('id', $request->only('location_id'))->first();

        return $this->handle($location, $pallet);
    }
}

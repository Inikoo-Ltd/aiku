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
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class UpdatePalletLocation extends OrgAction
{
    use WithActionUpdate;

    private Pallet $pallet;
    private mixed $scope;

    public function handle(Location $location, Pallet $pallet): Pallet
    {
        $oldLocation = $pallet->location;
        $pallet = $this->update($pallet, ['location_id' => $location->id]);

        $pallet->auditEvent    = 'update';
        $pallet->isCustomEvent = true;

        $pallet->auditCustomOld = [
            'location' => $oldLocation->code
        ];

        $pallet->auditCustomNew = [
            'location' => $location->code
        ];

        Event::dispatch(new AuditCustom($pallet));

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scope instanceof Warehouse) {
            $this->canEdit = $request->user()->authTo("locations.{$this->scope->id}.edit");

            return  $request->user()->authTo("locations.{$this->scope->id}.edit");
        }

        $this->canEdit = $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Location $location, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->scope  = $pallet->fulfilment;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($location, $pallet);
    }

    public function usingLocationSlug(Pallet $pallet, Location $location, ActionRequest $request): Pallet
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

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Warehouse $warehouse, Location $location, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->scope  = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        $newLocation = Location::where('id', $request->only('location_id'))->first();

        return $this->handle($newLocation, $pallet);
    }
}

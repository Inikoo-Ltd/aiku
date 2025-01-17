<?php

/*
 * author Arya Permana - Kirin
 * created on 16-01-2025-13h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Storage\PalletDelivery;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\Search\PalletDeliveryRecordSearch;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletDeliveries;
use App\Actions\RetinaAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletDeliveries;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class SubmitRetinaPalletDelivery extends RetinaAction
{
    use WithActionUpdate;

    private bool $action = false;
    private PalletDelivery $palletDelivery;

    public function handle(PalletDelivery $palletDelivery): PalletDelivery
    {
        $modelData['submitted_at'] = now();
        $modelData['state']        = PalletDeliveryStateEnum::SUBMITTED;

        $palletDelivery = $this->update($palletDelivery, $modelData);

        foreach ($palletDelivery->pallets as $pallet) {
            foreach ($pallet->storedItems as $storedItem) {
                UpdateStoredItem::run($storedItem, [
                    'state' => StoredItemStateEnum::SUBMITTED->value
                ]);
            }
        }

        SendPalletDeliveryNotification::dispatch($palletDelivery);

        GroupHydratePalletDeliveries::dispatch($palletDelivery->group);
        OrganisationHydratePalletDeliveries::dispatch($palletDelivery->organisation);
        WarehouseHydratePalletDeliveries::dispatch($palletDelivery->warehouse);
        FulfilmentCustomerHydratePalletDeliveries::dispatch($palletDelivery->fulfilmentCustomer);
        FulfilmentHydratePalletDeliveries::dispatch($palletDelivery->fulfilment);

        PalletDeliveryRecordSearch::dispatch($palletDelivery);
        return $palletDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        } elseif ($this->customer->id == $request->route()->parameter('palletDelivery')->fulfilmentCustomer->customer_id) {
            return true;
        } 
    }

    public function jsonResponse(PalletDelivery $palletDelivery): JsonResource
    {
        return new PalletDeliveryResource($palletDelivery);
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->palletDelivery = $palletDelivery;
        $this->initialisation($request);
        return $this->handle($palletDelivery);
    }

    public function action(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        $this->action       = true;
        $this->palletDelivery = $palletDelivery;
        $this->actionInitialisation($palletDelivery->fulfilmentCustomer, $modelData);
        return $this->handle($palletDelivery);
    }


}

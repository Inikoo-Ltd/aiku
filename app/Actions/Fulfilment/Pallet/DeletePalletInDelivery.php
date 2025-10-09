<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 23:00:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydratePallets;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePallets;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeletePalletInDelivery extends OrgAction
{
    use WithActionUpdate;


    private Pallet $pallet;

    public function handle(PalletDelivery $palletDelivery, Pallet $pallet): bool
    {

        $pallet->delete();
        PalletDeliveryHydratePallets::run($palletDelivery);
        FulfilmentCustomerHydratePallets::dispatch($pallet->fulfilmentCustomer);
        FulfilmentHydratePallets::dispatch($pallet->fulfilment);
        OrganisationHydratePallets::dispatch($pallet->organisation);
        WarehouseHydratePallets::dispatch($pallet->warehouse);
        PalletRecordSearch::dispatch($pallet);

        return true;
    }

    public function authorize(ActionRequest $request): bool
    {

        if (!($this->pallet->state == PalletStateEnum::IN_PROCESS || $this->pallet->state == PalletStateEnum::SUBMITTED)) {
            return false;
        }

        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            if (!$this->pallet->state == PalletStateEnum::IN_PROCESS) {
                return false;
            }

            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function asController(Organisation $organisation, FulfilmentCustomer $fulfilmentCustomer, PalletDelivery $palletDelivery, Pallet $pallet, ActionRequest $request): bool
    {
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($palletDelivery, $pallet);
    }

    public function fromRetina(PalletDelivery $palletDelivery, Pallet $pallet, ActionRequest $request): bool
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $this->pallet       = $pallet;
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;

        $this->initialisation($request->get('website')->organisation, $request);
        return $this->handle($palletDelivery, $pallet);
    }

    public function action(Pallet $pallet, int $hydratorsDelay = 0): bool
    {
        $this->pallet         = $pallet;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($pallet->fulfilment, []);

        return $this->handle($pallet->palletDelivery, $pallet);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 23:14:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeleteStoredItemFromReturn extends OrgAction
{
    use WithActionUpdate;


    private PalletReturnItem $palletReturnItem;

    public function handle(PalletReturn $palletReturn, PalletReturnItem $palletReturnItem): bool
    {

        $palletReturnItem->delete();

        PalletReturnHydratePallets::dispatch($palletReturn);

        return true;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function asController(Organisation $organisation, FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn, PalletReturnItem $palletReturnItem, ActionRequest $request): bool
    {
        $this->palletReturnItem = $palletReturnItem;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($palletReturn, $palletReturnItem);
    }

    public function fromRetina(PalletReturn $palletReturn, PalletReturnItem $palletReturnItem, ActionRequest $request): bool
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $this->palletReturnItem       = $palletReturnItem;
        $fulfilmentCustomer           = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment             = $fulfilmentCustomer->fulfilment;

        $this->initialisation($request->input('website')->organisation, $request);
        return $this->handle($palletReturn, $palletReturnItem);
    }
}

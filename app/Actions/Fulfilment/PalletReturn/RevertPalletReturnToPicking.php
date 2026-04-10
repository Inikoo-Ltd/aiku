<?php

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\Search\PalletReturnRecordSearch;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class RevertPalletReturnToPicking extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletReturn $palletReturn, array $modelData = []): PalletReturn
    {
        return DB::transaction(function () use ($palletReturn, $modelData) {
            $modelData['picked_at'] = null;
            $modelData['state'] = PalletReturnStateEnum::PICKING;

            $palletReturn = $this->update($palletReturn, $modelData);

            GroupHydratePalletReturns::dispatch($palletReturn->group);
            OrganisationHydratePalletReturns::dispatch($palletReturn->organisation);
            WarehouseHydratePalletReturns::dispatch($palletReturn->warehouse);
            FulfilmentCustomerHydratePalletReturns::dispatch($palletReturn->fulfilmentCustomer);
            FulfilmentHydratePalletReturns::dispatch($palletReturn->fulfilment);

            SendPalletReturnNotification::run($palletReturn);
            PalletReturnRecordSearch::dispatch($palletReturn);

            return $palletReturn;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $palletReturn = $request->route('palletReturn');
        if (!$palletReturn instanceof PalletReturn) {
            return false;
        }

        return $request->user()->authTo("fulfilment-shop.{$palletReturn->fulfilment_id}.edit");
    }
}

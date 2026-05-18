<?php

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\PickingSession;
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

            $pickingSessions = $palletReturn->pickingSessions()->get();
            foreach ($pickingSessions as $pickingSession) {
                if (!$this->shouldMovePickingSessionToPickingFinished($pickingSession)) {
                    continue;
                }

                $this->update($pickingSession, [
                    'state' => PickingSessionStateEnum::PICKING_FINISHED,
                    'end_at' => null,
                ]);

                WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);
            }

            SendPalletReturnNotification::run($palletReturn);

            return $palletReturn;
        });
    }

    private function shouldMovePickingSessionToPickingFinished(PickingSession $pickingSession): bool
    {
        $states = $pickingSession->palletReturns()
            ->toBase()
            ->select('pallet_returns.state')
            ->pluck('state');

        return $states->isNotEmpty()
            && $states->every(fn (string $state) => $state === PalletReturnStateEnum::PICKING->value);
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

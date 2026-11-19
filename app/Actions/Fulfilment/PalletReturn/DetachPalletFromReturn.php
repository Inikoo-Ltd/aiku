<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 23:14:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydrateTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Notifications\Work\WorkNotifier;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DetachPalletFromReturn extends OrgAction
{
    use WithActionUpdate;
    use WithFulfilmentShopEditAuthorisation;
    use WithPalletReturnPalletChanges;


    public function handle(PalletReturn $palletReturn, Pallet $pallet, bool $onlyWhileInProcess = false): bool
    {
        DB::transaction(function () use ($palletReturn, $pallet, $onlyWhileInProcess) {
            $this->lockPalletReturnForPalletChanges($palletReturn, $onlyWhileInProcess);

            $this->update($pallet, [
                'pallet_return_id'        => null,
                'status'                  => PalletStatusEnum::STORING,
                'state'                   => PalletStateEnum::STORING,
                'requested_for_return_at' => null
            ]);

            $palletReturn->pallets()->detach([$pallet->id]);
            WorkNotifier::modified($palletReturn, 'lines');
        });

        $palletReturn = SetPalletReturnAutoServices::run($palletReturn);
        PalletReturnHydratePallets::dispatch($palletReturn);
        PalletReturnHydrateTransactions::dispatch($palletReturn);

        return true;
    }

    public function asController(PalletReturn $palletReturn, Pallet $pallet, ActionRequest $request): bool
    {
        abort_unless($pallet->fulfilment_customer_id === $palletReturn->fulfilment_customer_id, 404);
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $pallet, onlyWhileInProcess: true);
    }

    public function action(Pallet $pallet, array $modelData, int $hydratorsDelay = 0): bool
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($pallet->fulfilment, $modelData);

        return $this->handle($pallet->palletReturn, $pallet);
    }

}

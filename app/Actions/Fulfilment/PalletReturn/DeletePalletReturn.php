<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class DeletePalletReturn extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private bool $action = false;
    private FulfilmentCustomer $fulfilmentCustomer;
    private PalletReturn $palletReturn;


    public function handle(PalletReturn $palletReturn): void
    {
        if (in_array($palletReturn->state, [PalletReturnStateEnum::IN_PROCESS, PalletReturnStateEnum::SUBMITTED])) {
            if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
                $palletIds = $palletReturn->pallets->pluck('id')->toArray();
                foreach ($palletReturn->pallets as $pallet) {
                    UpdatePallet::run($pallet, [
                        'state' => PalletStateEnum::STORING,
                        'status' => PalletStatusEnum::STORING,
                        'pallet_return_id' => null
                    ]);

                }
                $palletReturn->pallets()->detach($palletIds);
            } elseif ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
                $storedItemIds = $palletReturn->storedItems->pluck('id')->toArray();
                $palletReturn->storedItems()->detach($storedItemIds);
            }
            $palletReturn->transactions()->delete();
            $palletReturn->delete();
        } else {
            abort(401);
        }
    }

    public function htmlResponse(): Response
    {
        return Inertia::location(route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.index', [
            'organisation' => $this->organisation->slug,
            'fulfilment' => $this->fulfilment->slug,
            'fulfilmentCustomer' => $this->fulfilmentCustomer->slug
        ]));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function asController(Organisation $organisation, PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->fulfilmentCustomer = $palletReturn->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        $this->handle($palletReturn);
    }

    public function action(PalletReturn $palletReturn, $modelData): void
    {
        $this->action = true;
        $this->fulfilmentCustomer = $palletReturn->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);

        $this->handle($palletReturn);
    }
}

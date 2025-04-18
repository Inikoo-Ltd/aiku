<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Dropshipping\Shopify\Order\CancelFulfilmentRequestToShopify;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;

class DeletePalletReturn extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private bool $action = false;
    private FulfilmentCustomer $fulfilmentCustomer;
    private PalletReturn $palletReturn;


    public function handle(PalletReturn $palletReturn, array $modelData = []): void
    {
        if (in_array($palletReturn->state, [PalletReturnStateEnum::IN_PROCESS, PalletReturnStateEnum::SUBMITTED])) {
            if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
                $palletIds = $palletReturn->pallets->pluck('id')->toArray();
                foreach ($palletReturn->pallets as $pallet) {
                    UpdatePallet::run($pallet, [
                        'state'                   => PalletStateEnum::STORING,
                        'status'                  => PalletStatusEnum::STORING,
                        'pallet_return_id'        => null,
                        'requested_for_return_at' => null
                    ]);
                }
                $palletReturn->pallets()->detach($palletIds);
            } elseif ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
                $storedItemIds = $palletReturn->storedItems->pluck('id')->toArray();
                $palletReturn->storedItems()->detach($storedItemIds);
            }
            $palletReturn->transactions()->delete();

            $this->update($palletReturn, [
                'deleted_note' => Arr::get($modelData, 'deleted_note')
            ]);

            $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

            $fulfilmentCustomer->customer->auditEvent    = 'delete';
            $fulfilmentCustomer->customer->isCustomEvent = true;

            $fulfilmentCustomer->customer->auditCustomOld = [
                'return' => $palletReturn->reference
            ];

            $fulfilmentCustomer->customer->auditCustomNew = [
                'return' => __("The return has been deleted due to: $palletReturn->delete_comment.")
            ];

            Event::dispatch(AuditCustom::class, [$fulfilmentCustomer->customer]);

            if ($palletReturn->fulfilmentCustomer->customer->shopifyUser !== null) {
                CancelFulfilmentRequestToShopify::dispatch($palletReturn);
            }

            $palletReturn->delete();
            $fulfilmentCustomer->refresh();
            FulfilmentCustomerHydratePalletReturns::dispatch($fulfilmentCustomer);
            FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
        } else {
            abort(401);
        }
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.index', [
            'organisation'       => $this->organisation->slug,
            'fulfilment'         => $this->fulfilment->slug,
            'fulfilmentCustomer' => $this->fulfilmentCustomer->slug
        ]);
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

        $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, $modelData): void
    {
        $this->action             = true;
        $this->fulfilmentCustomer = $palletReturn->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);

        $this->handle($palletReturn, $this->validatedData);
    }
}

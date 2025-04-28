<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;

class DeletePalletDelivery extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public Customer $customer;
    private bool $action = false;
    private FulfilmentCustomer $fulfilmentCustomer;

    public function handle(PalletDelivery $palletDelivery, array $modelData = []): void
    {
        if (in_array($palletDelivery->state, [PalletDeliveryStateEnum::IN_PROCESS, PalletDeliveryStateEnum::SUBMITTED])) {
            $palletDelivery->pallets()->delete();
            $palletDelivery->transactions()->delete();

            $this->update($palletDelivery, [
                'deleted_note' => Arr::get($modelData, 'deleted_note')
            ]);

            $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

            $fulfilmentCustomer->customer->auditEvent    = 'delete';
            $fulfilmentCustomer->customer->isCustomEvent = true;

            $fulfilmentCustomer->customer->auditCustomOld = [
                'delivery' => $palletDelivery->reference
            ];

            $fulfilmentCustomer->customer->auditCustomNew = [
                'delivery' => __("The delivery has been deleted due to: $palletDelivery->delete_comment.")
            ];

            Event::dispatch(AuditCustom::class, [$fulfilmentCustomer->customer]);

            $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

            $palletDelivery->delete();

            $fulfilmentCustomer->refresh();
            FulfilmentCustomerHydratePalletDeliveries::dispatch($fulfilmentCustomer);
            FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
        } else {
            abort(401);
        }
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index', [
            'organisation' => $this->organisation->slug,
            'fulfilment' => $this->fulfilment->slug,
            'fulfilmentCustomer' => $this->fulfilmentCustomer->slug
        ]);
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function asController(Organisation $organisation, PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, $modelData): void
    {
        $this->action = true;
        $this->fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $modelData);

        $this->handle($palletDelivery, $this->validatedData);
    }
}

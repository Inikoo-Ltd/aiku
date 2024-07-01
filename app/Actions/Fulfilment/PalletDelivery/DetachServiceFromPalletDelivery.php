<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Jun 2024 22:48:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydrateServices;
use App\Actions\OrgAction;
use App\Enums\UI\Fulfilment\PalletDeliveryTabsEnum;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DetachServiceFromPalletDelivery extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public Customer $customer;

    public function handle(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        $palletDelivery->services()->detach([$modelData['service_id']]);

        PalletDeliveryHydrateServices::dispatch($palletDelivery);

        return $palletDelivery;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'quantity'   => ['required', 'integer', 'min:1']
        ];
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->initialisation($palletDelivery->organisation, $request->all());

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function fromRetina(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function htmlResponse(PalletDelivery $palletDelivery, ActionRequest $request): RedirectResponse
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.pallet-delivery.service.store' => Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
                'organisation'           => $palletDelivery->organisation->slug,
                'fulfilment'             => $palletDelivery->fulfilment->slug,
                'fulfilmentCustomer'     => $palletDelivery->fulfilmentCustomer->slug,
                'palletDelivery'         => $palletDelivery->slug,
                'tab'                    => PalletDeliveryTabsEnum::SERVICES->value
            ]),
            default => Redirect::route('retina.storage.pallet-deliveries.show', [
                'palletDelivery'     => $palletDelivery->slug
            ])
        };
    }
}

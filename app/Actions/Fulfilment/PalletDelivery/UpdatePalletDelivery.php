<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletDelivery\Search\PalletDeliveryRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class UpdatePalletDelivery extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public Customer $customer;
    private bool $action = false;
    /**
     * @var \App\Models\Fulfilment\PalletDelivery
     */
    private PalletDelivery $palletDelivery;

    public function handle(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        /** @var PalletDelivery $palletDelivery */
        $palletDelivery = $this->update($palletDelivery, $modelData);

        if ($palletDelivery->wasChanged('state')) {
            UpdatePalletDeliveryTimeline::run($palletDelivery, [
                'state' => $palletDelivery->state
            ]);
        }

        if ($palletDelivery->wasChanged('received_at')) {
            foreach ($palletDelivery->pallets as $pallet) {
                UpdatePallet::run($pallet, [
                    'received_at' => $palletDelivery->received_at
                ]);
            }
        }

        PalletDeliveryRecordSearch::dispatch($palletDelivery);

        return $palletDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        $rules = [];

        if (!request()->user() instanceof WebUser) {
            $rules = [
                'public_notes'   => ['sometimes', 'nullable', 'string', 'max:4000'],
                'internal_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            ];
        }

        return [
            'customer_reference'        => ['sometimes', 'nullable', 'string', Rule::unique('pallet_deliveries', 'customer_reference')
                ->ignore($this->palletDelivery->id)],
            'customer_notes'            => ['sometimes', 'nullable', 'string', 'max:4000'],
            'received_at' => ['sometimes', 'date'],
            'estimated_delivery_date'   => ['sometimes', 'date'],
            'current_recurring_bill_id' => [
                'sometimes',
                'nullable',
                Rule::exists('recurring_bills', 'id')->where('fulfilment_id', $this->fulfilment->id)
            ],
            ...$rules
        ];
    }

    public function fromRetina(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;

        $this->initialisation($request->input('website')->organisation, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }


    public function asController(Organisation $organisation, PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, $modelData): PalletDelivery
    {
        $this->action = true;
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $modelData);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    // public function htmlResponse(PalletDelivery $palletDelivery, ActionRequest $request): Response
    // {
    //     $routeName = $request->route()->getName();

    //     return match ($routeName) {
    //         'grp.models.pallet-delivery.update' => Inertia::location(route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
    //             'organisation'           => $palletDelivery->organisation->slug,
    //             'fulfilment'             => $palletDelivery->fulfilment->slug,
    //             'fulfilmentCustomer'     => $palletDelivery->fulfilmentCustomer->slug,
    //             'palletDelivery'         => $palletDelivery->slug
    //         ])),
    //         default => Inertia::location(route('retina.fulfilment.storage.pallet_deliveries.show', [
    //             'palletDelivery'         => $palletDelivery->slug
    //         ]))
    //     };
    // }

}

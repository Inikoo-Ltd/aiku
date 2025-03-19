<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydratePallets;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAutoServices;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePalletCreatedInPalletDelivery extends OrgAction
{
    private PalletDelivery $parent;

    /**
     * @throws \Throwable
     */
    public function handle(PalletDelivery $palletDelivery, array $modelData): Pallet
    {
        data_set($modelData, 'group_id', $palletDelivery->group_id);
        data_set($modelData, 'organisation_id', $palletDelivery->organisation_id);
        data_set($modelData, 'fulfilment_id', $palletDelivery->fulfilment_id);
        data_set($modelData, 'fulfilment_customer_id', $palletDelivery->fulfilment_customer_id);
        data_set($modelData, 'warehouse_id', $palletDelivery->warehouse_id);
        data_set($modelData, 'pallet_delivery_id', $palletDelivery->id);

        if (Arr::get($modelData, 'type')) {
            data_set($modelData, 'type', Arr::get($modelData, 'type'));
        }

        $totalPallets        = $palletDelivery->fulfilmentCustomer->pallets()->count();
        $numberStoredPallets = $palletDelivery->pallets()->where('state', PalletDeliveryStateEnum::BOOKED_IN->value)->count();
        $palletLimits        = $palletDelivery->fulfilmentCustomer->rentalAgreement->pallets_limit ?? 0;
        $palletLimitLeft     = ($palletLimits - ($totalPallets + $numberStoredPallets));

        if ($palletLimits > 0 && $palletLimitLeft <= 0) {
            abort(403, __("Pallet has reached over the limit: :palletLimitLeft", ['palletLimitLeft' => $palletLimitLeft]));
        }

        $pallet = StorePallet::make()->action($palletDelivery->fulfilmentCustomer, $modelData);


        SetPalletDeliveryAutoServices::run($palletDelivery);
        PalletDeliveryHydratePallets::run($palletDelivery);

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->fulfilment->warehouses()->count() == 1) {
            /** @var Warehouse $warehouse */
            $warehouse = $this->fulfilment->warehouses()->first();
            $this->fill(['warehouse_id' => $warehouse->id]);
        }
    }

    public function rules(): array
    {
        return [
            'type'               => ['nullable', Rule::enum(PalletTypeEnum::class)],
            'customer_reference' => ['nullable'],
            'notes'              => ['nullable', 'string','max:1024']
        ];
    }


    /**
     * @throws \Throwable
     */
    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): Pallet
    {
        $this->parent = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);
        return $this->handle($palletDelivery, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(PalletDelivery $palletDelivery, array $modelData, int $hydratorsDelay = 0): Pallet
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->parent         = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $modelData);

        return $this->handle($palletDelivery, $this->validatedData);
    }


    public function htmlResponse(Pallet $pallet, ActionRequest $request): RedirectResponse
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.pallet-delivery.pallet.store' => Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
                'organisation'           => $pallet->organisation->slug,
                'fulfilment'             => $pallet->fulfilment->slug,
                'fulfilmentCustomer'     => $pallet->fulfilmentCustomer->slug,
                'palletDelivery'         => $this->parent->slug
            ]),
            default => Redirect::route('retina.fulfilment.storage.pallet_deliveries.show', [
                'palletDelivery'     => $this->parent->slug
            ])
        };
    }
}

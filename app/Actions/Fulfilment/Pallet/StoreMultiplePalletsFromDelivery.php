<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreMultiplePalletsFromDelivery extends OrgAction
{
    private FulfilmentCustomer $fulfilmentCustomer;

    private PalletDelivery|FulfilmentCustomer $parent;

    public function handle(PalletDelivery $palletDelivery, array $modelData): void
    {
        data_set($modelData, 'warehouse_id', $palletDelivery->warehouse_id);

        for ($i = 1; $i <= Arr::get($modelData, 'number_pallets'); $i++) {
            StorePalletCreatedInPalletDelivery::run($palletDelivery, Arr::except($modelData, ['number_pallets']));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            // TODO: Raul please do the permission for the web user
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
            'warehouse_id'   => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
            'number_pallets' => ['required', 'integer', 'min:1', 'max:1000'],
            'type'           => ['required', Rule::enum(PalletTypeEnum::class)],
        ];
    }

    public function fromRetina(PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;
        $this->parent       = $palletDelivery;

        $this->initialisation($request->get('website')->organisation, $request);
        $this->handle($palletDelivery, $this->validatedData);
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->parent             = $palletDelivery;
        $this->fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
        $request->merge(
            [
                'warehouse_id' => $palletDelivery->warehouse_id
            ]
        );

        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, array $modelData, int $hydratorsDelay = 0): void
    {
        $this->asAction           = true;
        $this->hydratorsDelay     = $hydratorsDelay;
        $this->parent             = $palletDelivery;
        $this->fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletDelivery->fulfilmentCustomer->fulfilment, $modelData);

        $this->handle($palletDelivery, $this->validatedData);
    }


    public function htmlResponse(): RedirectResponse
    {
        $routeName = request()->route()->getName();

        return match ($routeName) {
            'retina.models.pallet-delivery.multiple-pallets.store' => Redirect::route('retina.fulfilment.storage.pallet_deliveries.show', [
                'palletDelivery' => $this->parent->slug
            ]),
            default => Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
                'organisation'       => $this->organisation->slug,
                'fulfilment'         => $this->fulfilment->slug,
                'fulfilmentCustomer' => $this->fulfilmentCustomer->slug,
                'palletDelivery'     => $this->parent->slug
            ]),
        };
    }
}

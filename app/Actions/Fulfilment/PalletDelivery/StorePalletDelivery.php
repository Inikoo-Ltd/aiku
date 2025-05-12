<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Catalogue\HasRentalAgreement;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\Search\PalletDeliveryRecordSearch;
use App\Actions\Fulfilment\WithDeliverableStoreProcessing;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletDeliveries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletDeliveries;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;

class StorePalletDelivery extends OrgAction
{
    use HasRentalAgreement;
    use WithDeliverableStoreProcessing;
    use WithFulfilmentShopEditAuthorisation;


    public Customer $customer;
    private FulfilmentCustomer $fulfilmentCustomer;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): PalletDelivery
    {
        if (!Arr::exists($modelData, 'tax_category_id')) {
            data_set(
                $modelData,
                'tax_category_id',
                GetTaxCategory::run(
                    country: $fulfilmentCustomer->organisation->country,
                    taxNumber: $fulfilmentCustomer->customer->taxNumber,
                    billingAddress: $fulfilmentCustomer->customer->address,
                    deliveryAddress: $fulfilmentCustomer->customer->address,
                )->id
            );
        }

        data_set($modelData, 'currency_id', $fulfilmentCustomer->fulfilment->shop->currency_id, overwrite: false);

        $modelData = $this->processData($modelData, $fulfilmentCustomer, SerialReferenceModelEnum::PALLET_DELIVERY);

        /** @var PalletDelivery $palletDelivery */
        $palletDelivery = $fulfilmentCustomer->palletDeliveries()->create($modelData);
        $palletDelivery->stats()->create();
        $palletDelivery->refresh();
        $palletDelivery = SetPalletDeliveryDate::run($palletDelivery);
        PalletDeliveryRecordSearch::dispatch($palletDelivery);

        GroupHydratePalletDeliveries::dispatch($fulfilmentCustomer->group);
        OrganisationHydratePalletDeliveries::dispatch($fulfilmentCustomer->organisation);
        WarehouseHydratePalletDeliveries::dispatch($palletDelivery->warehouse);
        FulfilmentCustomerHydratePalletDeliveries::dispatch($fulfilmentCustomer);
        FulfilmentHydratePalletDeliveries::dispatch($fulfilmentCustomer->fulfilment);


        SendPalletDeliveryNotification::dispatch($palletDelivery);

        return $palletDelivery;
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
        /** @noinspection DuplicatedCode */
        $rules = [];

        if (!request()->user() instanceof WebUser) {
            $rules = [
                'public_notes'   => ['sometimes', 'nullable', 'string', 'max:4000'],
                'internal_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            ];
        }

        return [
            'warehouse_id'   => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
            'customer_notes' => ['sometimes', 'nullable', 'string'],
            ...$rules
        ];
    }

    public function asController(Organisation $organisation, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): PalletDelivery
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, $modelData): PalletDelivery
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $modelData);
        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function jsonResponse(PalletDelivery $palletDelivery): array
    {
        return [
            'route' => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show',
                'parameters' => [
                    'organisation'       => $palletDelivery->organisation->slug,
                    'fulfilment'         => $palletDelivery->fulfilment->slug,
                    'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->slug,
                    'palletDelivery'     => $palletDelivery->reference
                ]
            ]
        ];
    }

    public function htmlResponse(PalletDelivery $palletDelivery, ActionRequest $request): RedirectResponse
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.fulfilment-customer.pallet-delivery.store' => Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
                'organisation'       => $palletDelivery->organisation->slug,
                'fulfilment'         => $palletDelivery->fulfilment->slug,
                'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->slug,
                'palletDelivery'     => $palletDelivery->slug
            ]),
            default => Redirect::route('retina.fulfilment.storage.pallet_deliveries.show', [
                'palletDelivery' => $palletDelivery->slug
            ])
        };
    }


}

<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 14 Feb 2024 16:17:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Storage\PalletReturn;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\Search\PalletReturnRecordSearch;
use App\Actions\Fulfilment\WithDeliverableStoreProcessing;
use App\Actions\Fulfilment\WithTaxCategoryTraits;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\RetinaAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreRetinaPalletReturn extends RetinaAction
{
    use WithDeliverableStoreProcessing;
    use WithModelAddressActions;
    use WithTaxCategoryTraits;

    public Customer $customer;

    private bool $action = false;

    private bool $withStoredItems = false;
    private Fulfilment $fulfilment;
    private Organisation $organisation;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): PalletReturn
    {
        if (!Arr::exists($modelData, 'tax_category_id')) {
            $this->processTaxCategory($modelData, $fulfilmentCustomer, $this->organisation);
        }

        data_set($modelData, 'currency_id', $fulfilmentCustomer->fulfilment->shop->currency_id, overwrite: false);

        $modelData = $this->processData($modelData, $fulfilmentCustomer, SerialReferenceModelEnum::PALLET_RETURN);

        /** @var PalletReturn $palletReturn */
        $palletReturn = $fulfilmentCustomer->palletReturns()->create($modelData);
        $palletReturn->stats()->create();


        $palletReturn = $this->attachAddressToModel(
            model: $palletReturn,
            address: $fulfilmentCustomer->customer->deliveryAddress,
            scope: 'delivery',
            updateLocation: false,
            updateAddressField: 'delivery_address_id'
        );


        $palletReturn->refresh();

        PalletReturnRecordSearch::dispatch($palletReturn);

        GroupHydratePalletReturns::dispatch($palletReturn->group);
        OrganisationHydratePalletReturns::dispatch($palletReturn->organisation);
        WarehouseHydratePalletReturns::dispatch($palletReturn->warehouse);
        FulfilmentCustomerHydratePalletReturns::dispatch($palletReturn->fulfilmentCustomer);
        FulfilmentHydratePalletReturns::dispatch($palletReturn->fulfilment);

        SendPalletReturnNotification::run($palletReturn);

        return $palletReturn;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->hasPermissionTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->fulfilment->warehouses()->count() == 1) {
            /** @var Warehouse $warehouse */
            $warehouse = $this->fulfilment->warehouses()->first();
            $this->fill(['warehouse_id' => $warehouse->id]);
        }

        if ($this->withStoredItems) {
            $this->set('type', PalletReturnTypeEnum::STORED_ITEM);
        } else {
            $this->set('type', PalletReturnTypeEnum::PALLET);
        }
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
            'type'           => ['sometimes', 'required', Rule::enum(PalletReturnTypeEnum::class)],
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


    public function asController(Organisation $organisation, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): PalletReturn
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;
        $this->organisation   = $organisation;

        $this->initialisation($request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function fromRetinaWithStoredItems(ActionRequest $request): PalletReturn
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $this->withStoredItems = true;
        $fulfilmentCustomer    = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment      = $fulfilmentCustomer->fulfilment;

        $this->initialisation($request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function jsonResponse(PalletReturn $palletReturn): array
    {
        return [
            'route' => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show',
                'parameters' => [
                    'organisation'       => $palletReturn->organisation->slug,
                    'fulfilment'         => $palletReturn->fulfilment->slug,
                    'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                    'palletReturn'       => $palletReturn->slug
                ]
            ]
        ];
    }

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): Response
    {
        return Inertia::location(route('retina.fulfilment.storage.pallet-returns.show', [
            'palletReturn' => $palletReturn->slug
        ]));
    }
}

<?php

/*
 * author Arya Permana - Kirin
 * created on 20-05-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;

class StoreRetinaPlatformPalletReturn extends RetinaAction
{
    public function handle(CustomerClient $customerClient, array $modelData): PalletReturn
    {
        data_set($modelData, 'customer_sales_channel_id', $customerClient->customer_sales_channel_id);
        data_set($modelData, 'platform_id', $customerClient->platform_id);
        data_set($modelData, 'type', PalletReturnTypeEnum::STORED_ITEM);

        return StorePalletReturn::run($customerClient->customer->fulfilmentCustomer, $modelData);
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
        ];
    }


    public function asController(CustomerClient $customerClient, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromPlatform($customerClient->platform, $request);

        return $this->handle($customerClient, $this->validatedData);
    }

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): RedirectResponse
    {
        return  Redirect::route('retina.fulfilment.dropshipping.customer_sales_channels.basket.show', [
            'customerSalesChannel' => $palletReturn->customerSaleChannel->slug,
            'palletReturn' => $palletReturn->slug
        ]);
    }
}

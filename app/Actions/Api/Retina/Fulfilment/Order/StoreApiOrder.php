<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Api\Retina\Fulfilment\Resource\PalletReturnApiResource;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreApiOrder extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerClient $customerClient): PalletReturn
    {
        data_set($modelData, 'customer_sales_channel_id', $customerClient->customer_sales_channel_id);
        data_set($modelData, 'platform_id', $customerClient->platform_id);
        data_set($modelData, 'type', PalletReturnTypeEnum::STORED_ITEM);
        data_set($modelData, 'warehouse_id', $customerClient->organisation->warehouses->first()->id);

        return StorePalletReturn::run($customerClient->customer->fulfilmentCustomer, $modelData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerClient $customerClient, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($customerClient);
    }

    public function jsonResponse(PalletReturn $palletReturn): PalletReturnApiResource
    {
        return PalletReturnApiResource::make($palletReturn)
            ->additional([
                'message' => __('Order created successfully'),
            ]);
    }
}

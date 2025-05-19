<?php
/*
 * author Arya Permana - Kirin
 * created on 19-05-2025-16h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Portfolio;

use App\Actions\Fulfilment\PalletDelivery\ImportPalletsInPalletDelivery;
use App\Actions\Retina\Dropshipping\Product\StoreRetinaProductManual;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithImportModel;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Helpers\Upload;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class SyncAllRetinaStoredItemsToPortfolios extends RetinaAction
{
    use WithImportModel;

    public function handle(CustomerSalesChannel $customerSalesChannel): FulfilmentCustomer
    {
        $fulfilmentCustomer = $this->fulfilmentCustomer;
        $storedItemIds = $fulfilmentCustomer->storedItems()->where('state', StoredItemStateEnum::ACTIVE)->pluck('id')->toArray();
        StoreRetinaProductManual::make()->action($customerSalesChannel, [
            'items' => $storedItemIds
        ]);

        return $fulfilmentCustomer;
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): FulfilmentCustomer
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }
}

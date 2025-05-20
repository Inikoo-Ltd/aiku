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
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
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

    public function handle(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {
        $existingPortfolios = $customerSalesChannel->portfolios()->where('item_type', 'StoredItem')->pluck('item_id')->toArray();
        $storedItemIds = $this->fulfilmentCustomer->storedItems()->where('state', StoredItemStateEnum::ACTIVE)->pluck('id')->toArray();

        $itemsToSync = array_diff($storedItemIds, $existingPortfolios);

        if (!empty($itemsToSync)) {
            StoreRetinaProductManual::make()->action($customerSalesChannel, [
                'items' => $storedItemIds
            ]);
        }

        return $customerSalesChannel;
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }
}

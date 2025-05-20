<?php

/*
 * author Arya Permana - Kirin
 * created on 19-05-2025-16h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Dropshipping\Portfolio;

use App\Actions\Retina\Dropshipping\Product\StoreRetinaProductManual;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SyncAllRetinaStoredItemsToPortfolios extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {

        $existingPortfolios = $customerSalesChannel->portfolios()->where('item_type', class_basename(StoredItem::class))->pluck('item_id')->toArray();
        $storedItemIds = $this->fulfilmentCustomer->storedItems()->where('state', StoredItemStateEnum::ACTIVE)->pluck('id')->toArray();

        $itemsToSync = array_diff($storedItemIds, $existingPortfolios);

        if (empty($itemsToSync)) {
            throw ValidationException::withMessages([
                'messages' => __('All stored items have already been added as portfolios.')
            ]);
        }

        if (!empty($itemsToSync)) {
            StoreRetinaProductManual::make()->action($customerSalesChannel, [
                'items' => $storedItemIds
            ]);
        }

        return $customerSalesChannel;
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel);
    }
}

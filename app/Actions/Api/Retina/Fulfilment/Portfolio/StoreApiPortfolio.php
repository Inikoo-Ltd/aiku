<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-10h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Portfolio;

use App\Actions\Retina\Fulfilment\Dropshipping\Portfolio\SyncAllRetinaStoredItemsToPortfolios;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class StoreApiPortfolio extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        SyncAllRetinaStoredItemsToPortfolios::make()->action($customerSalesChannel);
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromFulfilment($request);

        $this->handle($this->customerSalesChannel);
    }

    public function jsonResponse(): JsonResource
    {
        return JsonResource::make([
            'message' => __('Portfolio synced successfully'),
        ]);
    }
}

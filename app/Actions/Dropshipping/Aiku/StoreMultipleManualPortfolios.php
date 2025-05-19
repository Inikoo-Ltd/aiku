<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreMultipleManualPortfolios extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        DB::transaction(function () use ($customerSalesChannel, $modelData) {
            foreach (Arr::get($modelData, 'items') as $itemID) {
                $itemID = (int) $itemID;
                if ($customerSalesChannel->customer->is_fulfilment) {
                    /** @var StoredItem $item */
                    $item = StoredItem::find($itemID);
                } else {
                    /** @var Product $item */
                    $item = Product::find($itemID);
                }
                if ($item->portfolio()->where('customer_sales_channel_id', $customerSalesChannel->id)->exists()) {
                    continue;
                }

                StorePortfolio::make()->action(
                    customerSalesChannel: $customerSalesChannel,
                    item: $item,
                    modelData: []
                );
            }
        });



        CustomerSalesChannelsHydratePortfolios::dispatch($customerSalesChannel);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}

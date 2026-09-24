<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreMultiplePortfolios extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
            throw ValidationException::withMessages([
                'customer_sales_channel' => __('This channel has already been deleted, products can not be added to it.')
            ]);
        }

        $itemIds      = array_map(intval(...), Arr::get($modelData, 'items'));
        $isFulfilment = $customerSalesChannel->customer->is_fulfilment;
        $itemModel    = $isFulfilment ? new StoredItem() : new Product();

        $items = $itemModel->newQuery()->whereIn('id', $itemIds)->get()->keyBy('id');

        $existingPortfolios = $customerSalesChannel->portfolios()
            ->where('item_type', $itemModel->getMorphClass())
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');

        foreach ($itemIds as $itemID) {
            /** @var Product|StoredItem $item */
            $item = $items->get($itemID);
            if (!$item) {
                continue;
            }

            if ($portfolio = $existingPortfolios->get($itemID)) {
                if (!$portfolio->status) {
                    UpdatePortfolio::make()->action($portfolio, [
                        'status' => true
                    ]);
                }

                continue;
            }

            StorePortfolio::make()->action(
                customerSalesChannel: $customerSalesChannel,
                item: $item,
                modelData: [],
                hydrateChannel: false
            );
        }


        CustomerSalesChannelsHydratePortfolios::run($customerSalesChannel);
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

    /**
     * @throws \Throwable
     */
    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $modelData);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}

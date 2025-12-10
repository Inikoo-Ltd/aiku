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
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Events\CloneRetinaPortfolioProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneFromChannelToChannel implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(CustomerSalesChannel $fromChannel, CustomerSalesChannel $toChannel): string
    {
        return $fromChannel->id .'='. $toChannel->id;
    }

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $fromChannel, CustomerSalesChannel $toChannel): void
    {
        if ($fromChannel->id == $toChannel->id) {
            return;
        }

        if ($fromChannel->customer_id != $toChannel->customer_id) {
            return;
        }

        $items = $fromChannel->portfolios()->pluck('item_id')->toArray();
        $total = count($items);

        if ($total === 0) {
            return;
        }

        $userId = $toChannel->customer->webUsers->first()?->id ?? 0;
        $actionId = $toChannel->id;
        $done = 0;
        $numberSuccess = 0;
        $numberFails = 0;

        $this->broadcastProgress($userId, $actionId, $total, $done, $numberSuccess, $numberFails);

        foreach ($items as $itemID) {
            try {
                $itemID = (int)$itemID;

                if ($toChannel->customer->is_fulfilment) {
                    /** @var StoredItem $item */
                    $item = StoredItem::find($itemID);
                } else {
                    /** @var Product $item */
                    $item = Product::find($itemID);
                }

                if (!$item) {
                    $numberFails++;
                    $done++;
                    $this->broadcastProgress($userId, $actionId, $total, $done, $numberSuccess, $numberFails);
                    continue;
                }

                if ($item->portfolios()->where('customer_sales_channel_id', $toChannel->id)->exists()) {
                    if ($portfolio = $item->portfolios()->where('customer_sales_channel_id', $toChannel->id)->where('status', false)->first()) {
                        UpdatePortfolio::make()->action($portfolio, [
                            'status' => true
                        ]);
                    }
                    $numberSuccess++;
                } else {
                    StorePortfolio::make()->action(
                        customerSalesChannel: $toChannel,
                        item: $item,
                        modelData: []
                    );
                    $numberSuccess++;
                }
            } catch (\Throwable $e) {
                $numberFails++;
            }

            $done++;

            $this->broadcastProgress($userId, $actionId, $total, $done, $numberSuccess, $numberFails);
        }

        CustomerSalesChannelsHydratePortfolios::run($toChannel);
    }

    private function broadcastProgress(int $userId, int $actionId, int $total, int $done, int $numberSuccess, int $numberFails): void
    {
        try {
            CloneRetinaPortfolioProgressEvent::dispatch(
                $userId,
                $actionId,
                'ClonePortfolio',
                $total,
                $done,
                $numberSuccess,
                $numberFails
            );
        } catch (\Throwable $e) {
            Log::warning('Clone progress broadcast failed: ' . $e->getMessage());
        }
    }
}

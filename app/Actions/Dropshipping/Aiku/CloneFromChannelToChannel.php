<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\Dropshipping\Portfolio\StoreMultiplePortfolios;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneFromChannelToChannel implements ShouldBeUnique
{
    use AsAction;

    public string $queue = 'long-running';


    public function getJobUniqueId(CustomerSalesChannel $fromChannel, CustomerSalesChannel $toChannel): string
    {
        return $fromChannel->id .'='. $toChannel->id;
    }

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $fromChannel, CustomerSalesChannel $toChannel, ?int $userId = null): void
    {
        if ($fromChannel->id == $toChannel->id) {
            return;
        }

        if ($fromChannel->customer_id != $toChannel->customer_id) {
            return;
        }

        $items = $fromChannel->portfolios()->pluck('item_id')->toArray();
        $total = count($items);
        $done = 0;
        $success = 0;
        $fails = 0;
        $actionId = time();

        if ($userId) {
            \App\Events\CloneRetinaPortfolioProgressEvent::dispatch($userId, $actionId, 'Upload', $total, $done, $success, $fails);
        }

        foreach ($items as $itemID) {
            try {
                if ($toChannel->customer->is_fulfilment) {
                    /** @var \App\Models\Fulfilment\StoredItem $item */
                    $item = \App\Models\Fulfilment\StoredItem::find($itemID);
                } else {
                    /** @var \App\Models\Catalogue\Product $item */
                    $item = \App\Models\Catalogue\Product::find($itemID);
                }

                if ($item) {
                    if ($item->portfolios()->where('customer_sales_channel_id', $toChannel->id)->exists()) {
                        if ($portfolio = $item->portfolios()->where('customer_sales_channel_id', $toChannel->id)->where('status', false)->first()) {
                            \App\Actions\Dropshipping\Portfolio\UpdatePortfolio::make()->action($portfolio, [
                                'status' => true
                            ]);
                        }
                    } else {
                        \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action(
                            customerSalesChannel: $toChannel,
                            item: $item,
                            modelData: []
                        );
                    }
                    $success++;
                } else {
                    $fails++;
                }
            } catch (\Exception $e) {
                $fails++;
            }

            $done++;

            if ($userId) {
                \App\Events\CloneRetinaPortfolioProgressEvent::dispatch($userId, $actionId, 'Upload', $total, $done, $success, $fails);
            }
        }

        \App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios::run($toChannel);
    }


}

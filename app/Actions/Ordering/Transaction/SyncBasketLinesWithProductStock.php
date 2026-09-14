<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateCategoriesData;
use App\Actions\Ordering\Order\LogBasketEvent;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A basket line for a product that went out of stock is set to zero quantity and zero amount, so
 * the customer is never charged for it. The quantity they asked for is kept on the line and put
 * back the moment the product is in stock again, as long as the order is still a basket.
 *
 * An on demand product is made to order, so it is never zeroed for lack of stock.
 * Baskets fetched from a sales platform, and orders of external shops, belong to the platform:
 * the merchant's customer already paid for that quantity there, so those lines are left alone.
 */
class SyncBasketLinesWithProductStock implements ShouldBeUnique
{
    use AsAction;

    public const string HELD_QUANTITY_KEY = 'out_of_stock_held_quantity';

    public string $jobQueue = 'urgent';
    public int $jobUniqueFor = 600;

    public function getJobUniqueId(Product $product): string
    {
        return (string) $product->id;
    }

    public function handle(Product $product): void
    {
        if ($product->is_on_demand) {
            return;
        }

        $lines = Transaction::where('model_type', 'Product')
            ->where('model_id', $product->id)
            ->whereHas('order', function ($query) {
                $query->where('state', OrderStateEnum::CREATING)
                    ->whereNull('platform_order_id')
                    ->whereHas('shop', fn ($shop) => $shop->where('type', '!=', ShopTypeEnum::EXTERNAL));
            })
            ->get();

        foreach ($lines as $line) {
            if (($product->refresh()->available_quantity ?? 0) <= 0) {
                $this->nil($line);
            } else {
                $this->restore($line);
            }
        }
    }

    private function nil(Transaction $line): void
    {
        if ($line->quantity_ordered <= 0) {
            return;
        }

        $held = (float) $line->quantity_ordered;
        $line->update([
            'quantity_ordered' => 0,
            'gross_amount'     => 0,
            'net_amount'       => 0,
            'org_net_amount'   => 0,
            'grp_net_amount'   => 0,
            'estimated_weight' => 0,
            'data'             => array_merge($line->data ?? [], [self::HELD_QUANTITY_KEY => $held]),
        ]);
        $this->recalculateOrder($line, -$held);
    }

    private function restore(Transaction $line): void
    {
        $held = Arr::get($line->data, self::HELD_QUANTITY_KEY);
        if (!$held || $line->quantity_ordered > 0) {
            return;
        }

        UpdateTransaction::make()->action($line, ['quantity_ordered' => $held]);
    }

    private function recalculateOrder(Transaction $line, float $delta): void
    {
        $order = $line->order;
        OrderHydrateCategoriesData::run($order);
        CalculateOrderTotalAmounts::run($order);
        LogBasketEvent::run($order->fresh(), 'down', $line, $delta);
    }
}

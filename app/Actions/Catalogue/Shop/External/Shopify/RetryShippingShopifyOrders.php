<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class RetryShippingShopifyOrders extends OrgAction
{
    public const int LOOKBACK_DAYS = 7;

    public string $commandSignature = 'external_shop:shopify_retry_shipping';

    /**
     * Shopify is told about a shipment when its label is made; an order dispatched without a label, or whose
     * update failed, is told here so no customer is left without a fulfilled order.
     */
    public function handle(?Command $command = null): int
    {
        $retried = 0;

        Order::query()
            ->whereHas('shop', fn ($query) => $query
                ->where('type', ShopTypeEnum::EXTERNAL)
                ->where('engine', ShopEngineEnum::SHOPIFY)
                ->where('state', ShopStateEnum::OPEN))
            ->where('state', OrderStateEnum::DISPATCHED)
            ->where('dispatched_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->whereNotNull('marketplace_id')
            ->where(fn ($query) => $query->where('is_shipping_by_external', false)->orWhereNull('is_shipping_by_external'))
            ->whereNull('data->shopify_fulfilled_at')
            ->chunkById(50, function ($orders) use (&$retried, $command) {
                foreach ($orders as $order) {
                    $deliveryNote = $order->deliveryNotes()
                        ->where('state', DeliveryNoteStateEnum::DISPATCHED)
                        ->latest('id')
                        ->first();

                    if (!$deliveryNote) {
                        continue;
                    }

                    UpdateShippingShopifyOrder::run($deliveryNote, $command);
                    $retried++;
                }
            });

        return $retried;
    }

    public function asCommand(Command $command): int
    {
        $command->info('Retried '.$this->handle($command).' Shopify orders');

        return 0;
    }
}

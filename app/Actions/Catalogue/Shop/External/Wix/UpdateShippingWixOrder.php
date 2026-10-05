<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class UpdateShippingWixOrder extends OrgAction
{
    use WithWixExternalShopApi;

    public function handle(DeliveryNote $deliveryNote, Command|null $command = null): array
    {
        if (!app()->isProduction()) {
            return [
                'status' => 'success',
                'msg'    => __('Order not updated in local environment')
            ];
        }

        $order = $deliveryNote->orders()->first();

        if (!$order || $order->shop->type != ShopTypeEnum::EXTERNAL || $order->shop->engine != ShopEngineEnum::WIX || !$order->external_id || $order->is_shipping_by_external) {
            return [
                'status' => 'fail',
                'msg'    => __('Unprocessable order')
            ];
        }

        $wixUser = WixUser::where('external_shop_id', $order->shop_id)->first();

        if (!$wixUser) {
            return [
                'status' => 'fail',
                'msg'    => __('Shop is not connected to Wix')
            ];
        }

        try {
            $lineItems = $order->transactions
                ->whereNotNull('external_id')
                ->map(fn ($transaction) => [
                    'id'       => $transaction->external_id,
                    'quantity' => (int) $transaction->quantity_dispatched ?: (int) $transaction->quantity_ordered,
                ])
                ->filter(fn ($lineItem) => $lineItem['quantity'] > 0)
                ->values()
                ->all();

            $fulfillment = [
                'lineItems' => $lineItems,
                'status'    => 'Fulfilled'
            ];

            $shipment = $deliveryNote->shipments()->latest()->first();

            if ($shipment?->tracking) {
                $fulfillment['trackingInfo'] = [
                    'trackingNumber'   => trim(Arr::first(preg_split('/[,\/]+/', $shipment->tracking, -1, PREG_SPLIT_NO_EMPTY)) ?? $shipment->tracking),
                    'shippingProvider' => $shipment->shipper?->trade_as ?: 'other'
                ];

                if ($trackingLink = Arr::first($shipment->tracking_urls ?? [])) {
                    $fulfillment['trackingInfo']['trackingLink'] = $trackingLink;
                }
            }

            $result = $this->createWixOrderFulfillment($wixUser, $order->external_id, $fulfillment);

            if (Arr::has($result, 'message')) {
                Sentry::captureMessage('Wix fulfillment failed for order '.$order->external_id.': '.Arr::get($result, 'message'));

                return [
                    'status' => 'fail',
                    'msg'    => Arr::get($result, 'message')
                ];
            }

            return [
                'status' => 'success',
                'msg'    => __('Wix order updated successfully')
            ];
        } catch (\Exception $e) {
            $command?->error('Order '.$order->external_id.' not updated '.$e->getMessage());
            Sentry::captureException($e);

            return [
                'status' => 'fail',
                'msg'    => $e->getMessage()
            ];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shop:update_shipping_wix_order {delivery_note}';
    }

    public function asCommand(Command $command): void
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();
        $result       = $this->handle($deliveryNote, $command);
        if ($result['status'] == 'fail') {
            $command->error($result['msg']);
        } else {
            $command->info($result['msg']);
        }
    }
}

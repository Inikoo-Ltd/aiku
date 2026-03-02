<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class UpdateShippingFaireOrder extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, Command|null $command = null): array
    {

        if(!app()->isProduction()){
            return [
                'status' => 'success',
                'msg'    => __('Order not updated in local environment')
            ];
        }

        $order = $deliveryNote->orders()->first();
        if (
            $order && $order->shop->type == ShopTypeEnum::EXTERNAL && $order->external_id && !$order->is_shipping_by_external
            && app()->isProduction()
        ) {
            try {
                $shipments = [];


                foreach ($deliveryNote->shipments as $shipment) {
                    $cost = $shipment->cost ? (int)($shipment->cost * 100) : 0;

                    $trackings = preg_split('/[,\/]+/', $shipment->tracking, -1, PREG_SPLIT_NO_EMPTY);


                    $cost = (int)($cost / count($trackings));

                    foreach ($trackings as $tracking) {
                        $shipments[] = [

                            'carrier'       => $shipment->shipper->trade_as,
                            'tracking_code' => trim($tracking),
                            'maker_cost'    => [
                                'amount_minor' => $cost,
                                'currency'      => $order->currency->code,
                            ],

                        ];
                    }
                }


                $result = $order->shop->updateShippingFaireOrder($order->external_id, [
                    'shipments' => $shipments,
                ]);


                if (!$result['success']) {
                    return [
                        'status' => 'fail',
                        'msg'    => Arr::get($result, 'error.message')
                    ];
                } else {
                    return [
                        'status' => 'success',
                        'msg'    => __('Faire order updated successfully')
                    ];
                }
            } catch (\Exception $e) {
                $command?->error('Order '.$order->external_id.' not updated '.$e->getMessage());
                Sentry::captureException($e);

                return [
                    'status' => 'fail',
                    'msg'    => $e->getMessage()
                ];
            }
        } else {
            return [
                'status' => 'fail',
                'msg'    => __('Unprocessable order')
            ];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shop:update_shipping_faire_order {delivery_note}';
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

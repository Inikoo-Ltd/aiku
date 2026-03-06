<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Resource;

use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderApiResource extends JsonResource
{
    use IsOrder;
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;

        $shippingData = is_array($order->shipping_data) ? $order->shipping_data : [];
        $firstShippingData = $shippingData[0] ?? null;

        return [
            'id'            => $order->id,
            'reference'     => $order->reference,
            'state'         => $order->state,
            'net_amount'    => $order->net_amount,
            'total_amount'    => $order->total_amount,
            'item_amount' => $order->goods_amount,
            'item_quantity' => $order->stats->number_item_transactions,
            'tax_amount' => $order->tax_amount,
            'shipping_amount' => $order->shipping_amount,
            'charges_amount' => $order->charges_amount,
            'date'          => $order->date,
            'customer' => CustomerResource::make($order->customer)->getArray(),
            'delivery_address' => AddressResource::make($order->deliveryAddress ?? new Address()),
            'billing_address' => AddressResource::make($order->billingAddress ?? new Address()),
            'products' => [
                'payment'          => [
                    'total_amount' => (float)$order->total_amount,
                    'paid_amount'  => (float)$order->payment_amount,
                    'pay_amount'   => $roundedDiff,
                ],
                'estimated_weight' => $estWeight
            ],
            'shipping_data' => [
                'shipping_id'       => data_get($firstShippingData, 'shipping_id'),
                'trackings'         => data_get($firstShippingData, 'trackings'),
                'tracking_urls'     => data_get($firstShippingData, 'tracking_urls'),
                'shipper_label'     => data_get($firstShippingData, 'shipper_label'),
            ],
             'transactions' => TransactionsApiResource::collection($order->transactions),
             'payments' => PaymentsApiResource::collection($order->payments),
        ];
    }
}

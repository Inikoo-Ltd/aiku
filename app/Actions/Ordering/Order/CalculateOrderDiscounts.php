<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 Oct 2025 16:13:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateOrderDiscounts
{
    use AsObject;

    private \Illuminate\Support\Collection $transactions;

    private array $enabledOffers = [];
    private array $offerMeters = [];

    private Order $order;

    public function handle(Order $order): Order
    {
        $this->order        = $order;
        $this->transactions = collect();

        $this->setEnabledOffers($order);
        if (count($this->enabledOffers) > 0) {
            $this->transactions = DB::table('transactions')
                ->select(['id', 'quantity_ordered', 'gross_amount', 'model_type', 'model_id'])
                ->where('order_id', $order->id)->where('model_type', 'Product')->get();

            $this->processAllowances();
        }

        DB::table('transaction_has_offer_allowances')->where('order_id', $order->id)->delete();
        DB::table('transactions')->where('order_id', $order->id)->update([
            'net_amount'  => DB::raw('gross_amount'),
            'offers_data' => []
        ]);

        foreach ($this->transactions as $transaction) {
            DB::table('transactions')->where('id', $transaction->id)
                ->update(
                    [
                        'gross_amount' => $transaction->gross_amount,
                        'net_amount'   => $transaction->net_amount,
                        'offers_data'  => [
                            'v' => 1,
                            'o' => [
                                'oc' => $transaction->offer_campaign_id,
                                'o'  => $transaction->offer_id,
                                'oa' => $transaction->offer_allowance_id,
                                't'  => $transaction->allowance_type,
                                'p'  => percentage($transaction->discounted_percentage, 1),
                                'l'  => $transaction->offer_label

                            ]
                        ]
                    ]
                );

            DB::table('transaction_has_offer_allowances')->insert([
                'order_id'              => $order->id,
                'transaction_id'        => $transaction->id,
                'model_type'            => $transaction->model_type,
                'model_id'              => $transaction->model_id,
                'offer_campaign_id'     => $transaction->offer_campaign_id,
                'offer_id'              => $transaction->offer_id,
                'offer_allowance_id'    => $transaction->offer_allowance_id,
                'discounted_amount'     => $transaction->discounted_amount,
                'discounted_percentage' => $transaction->discounted_percentage,
                'free_items_value'      => $transaction->free_items_value ?? 0,
                'number_of_free_items'  => $transaction->number_of_free_items ?? 0,
                'created_at'            => now(),
                'updated_at'            => now(),
                'data'                  => '{}'

            ]);
        }

        CalculateOrderTotalAmounts::run(order: $order, calculateShipping: true, calculateDiscounts: false);

        $order->update(
            [
                'offer_meters' => $this->offerMeters
            ]
        );


        return $order;
    }

    private function setEnabledOffers(Order $order): void
    {
        $enabledOffers = [];

        $offersData = DB::table('offers')->select(['id', 'type', 'trigger_data', 'allowance_signature', 'name'])->where('shop_id', $order->shop_id)->where('status', true)->where('trigger_type', 'Customer')->get();
        foreach ($offersData as $offerData) {
            if ($offerData->type == 'Amount AND Order Number') {
                list($passAmount, $passOrderNumber, $metadata) = $this->checkAmountAndOrderNumber($order, $offerData);
                if ($passAmount && $passOrderNumber) {
                    $enabledOffers[$offerData->allowance_signature] = [
                        'offer_id'    => $offerData->id,
                        'offer_label' => $offerData->name

                    ];
                }
                if ($passOrderNumber) {
                    $this->offerMeters[$offerData->allowance_signature] = [
                        'offer_id' => $offerData->id,
                        'label'    => $offerData->name,
                        'metadata' => $metadata,
                    ];
                }
            }
        }

        $this->enabledOffers = $enabledOffers;
    }

    public function checkAmountAndOrderNumber($order, $offerData): array
    {
        $passAmount      = false;
        $passOrderNumber = false;
        $metadata        = [];

        $triggerData = json_decode($offerData->trigger_data, true);

        if ($order->gross_amount >= $triggerData['min_amount']) {
            $passAmount = true;
        }

        $numberOrders = DB::table('orders')->where('customer_id', $order->customer_id)
            ->whereNotIn('state', [
                OrderStateEnum::CANCELLED->value,
                OrderStateEnum::CREATING->value,
            ])->count();

        if ($numberOrders == ($triggerData['order_number'] - 1)) {
            $passOrderNumber = true;

            $metadata = [
                'current' => $order->gross_amount,
                'target'  => $triggerData['min_amount'],
            ];
        }

        return [
            $passAmount,
            $passOrderNumber,
            $metadata
        ];
    }

    public function processAllowances(): void
    {
        foreach ($this->enabledOffers as $offerData) {
            $this->processAllowance($offerData);
        }
    }

    public function processAllowance(array $offerData): void
    {
        $allowanceData = DB::table('offer_allowances')->select(['target_type', 'data', 'offer_id', 'id', 'offer_campaign_id'])->where('offer_id', $offerData['offer_id'])->first();
        if ($allowanceData && $allowanceData->target_type == 'all_products_in_order') {
            $this->processAllowanceAllProductsInOrder($offerData, $allowanceData);
        }
    }

    public function processAllowanceAllProductsInOrder(array $offerData, $allowanceData): void
    {
        $allowanceOpsData = json_decode($allowanceData->data, true) ?? [];
        $percentageOff    = isset($allowanceOpsData['percentage_off']) ? (float)$allowanceOpsData['percentage_off'] : 0.0;


        // Clamp to [0,1]
        if ($percentageOff < 0) {
            $percentageOff = 0.0;
        } elseif ($percentageOff > 1) {
            $percentageOff = 1.0;
        }

        if ($percentageOff <= 0) {
            // Nothing to apply
            return;
        }


        foreach ($this->transactions as $transaction) {
            $current = property_exists($transaction, 'percentage_off') ? $transaction->percentage_off : null;

            // Apply only if undefined or lower than the new percentage
            if ($current === null || (is_numeric($current) && (float)$current < $percentageOff)) {
                $discountedAmount = round((float)$transaction->gross_amount * $percentageOff, 2);

                $transaction->discounted_percentage = $percentageOff;
                $transaction->net_amount            = $transaction->gross_amount - $discountedAmount;
                $transaction->discounted_amount     = $discountedAmount;
                $transaction->offer_id              = $allowanceData->offer_id;
                $transaction->offer_campaign_id     = $allowanceData->offer_campaign_id;
                $transaction->offer_allowance_id    = $allowanceData->id;
                $transaction->offer_label           = $offerData['offer_label'];
                $transaction->allowance_type        = 'percentage';
            }
        }
    }

}

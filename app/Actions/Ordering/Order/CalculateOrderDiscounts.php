<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 Oct 2025 16:13:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Discounts\OfferAllowance;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateOrderDiscounts implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    private \Illuminate\Support\Collection $transactions;

    private array $enabledOffers = [];
    private array $offerMeters = [];
    private bool $isLastInvoicedSet = false;
    private bool $isGrAmnestyOfferIdSet = false;
    private int|null $daysSinceLastInvoiced = null;
    private int|null $grAmnestyOfferId = null;


    private Order $order;

    public function getJobUniqueId(Order $order): string
    {
        return $order->id;
    }

    public function handle(Order $order): Order
    {
        if (in_array($order->state, [
            OrderStateEnum::CANCELLED,
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
        ])) {
            return $order;
        }

        $this->order        = $order;
        $this->transactions = collect();

        $this->setEnabledOffers($order);


        if (!empty($this->enabledOffers) || !empty($order->discretionary_offers_data)) {
            $this->transactions = DB::table('transactions')
                ->select([
                    'id',
                    'quantity_ordered',
                    'gross_amount',
                    'model_type',
                    'model_id',
                    'family_id',
                    'sub_department_id',
                    'department_id'
                ])
                ->where('order_id', $order->id)
                ->where('quantity_ordered', '>', 0)
                ->where('model_type', 'Product')
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id');

            $this->processAllowances();
        }
        $this->processDiscretionaryOffers($order);

        DB::table('transaction_has_offer_allowances')
            ->where('is_gift', false)
            ->where('order_id', $order->id)->delete();
        DB::table('transactions')->where('order_id', $order->id)
            ->where('quantity_ordered', '>', 0)
            ->update([
                'net_amount'              => DB::raw('gross_amount'),
                'offers_data'             => [],
                'current_discount_factor' => 1,
            ]);

        foreach ($this->transactions as $transaction) {
            if (property_exists($transaction, 'with_offer')) {
                $this->updateTransactionDiscount(
                    $order,
                    $transaction,
                    $transaction->discounted_percentage,
                    $transaction->discounted_amount,
                    [
                        'v' => 1,
                        'o' => [
                            'oc'  => $transaction->offer_campaign_id,
                            'o'   => $transaction->offer_id,
                            'oa'  => $transaction->offer_allowance_id,
                            't'   => $transaction->allowance_type,
                            'p'   => percentage($transaction->discounted_percentage, 1),
                            'l'   => $transaction->offer_label,
                            'st'  => $transaction->sub_trigger,
                            'sto' => $transaction->sub_trigger_offer_id,
                            'f'   => $transaction->free_items_value ?? 0,
                            'nf'  => $transaction->number_of_free_items ?? 0
                        ]
                    ]
                );
            }
        }


        if ($order->state != OrderStateEnum::CREATING) {
            $this->regenerateSubmittedTransactionDiscounts($order);
        }

        CalculateOrderTotalAmounts::run(order: $order, calculateShipping: true, calculateDiscounts: false);

        $this->getGiftsMeters($order);


        $order->update(
            [
                'offer_meters' => $this->offerMeters
            ]
        );


        return $order;
    }

    public function regenerateSubmittedTransactionDiscounts(Order $order): void
    {
        /** @var Transaction $transactionWithSubmittedDiscount */
        foreach (
            $order->transactions()
                ->where('has_discount_when_submitted', true)
                ->whereRaw("submitted_offers_data <> '{}'::jsonb")
                ->where('submitted_discount_factor', '<', DB::raw('current_discount_factor'))
                ->get() as $transactionWithSubmittedDiscount
        ) {
            DB::table('transaction_has_offer_allowances')->where('is_gift', false)->where('transaction_id', $transactionWithSubmittedDiscount->id)->delete();

            $percentageOff    = 1 - $transactionWithSubmittedDiscount->submitted_discount_factor;
            $discountedAmount = round((float)$transactionWithSubmittedDiscount->gross_amount * $percentageOff, 2);

            $this->updateTransactionDiscount(
                $order,
                $transactionWithSubmittedDiscount,
                $percentageOff,
                $discountedAmount,
                $transactionWithSubmittedDiscount->submitted_offers_data
            );
        }
    }


    public function getGiftsMeters(Order $order): void
    {
        foreach (
            DB::table('offers')
                ->select(['id', 'trigger_data', 'allowance_signature', 'name'])
                ->where('shop_id', $order->shop_id)
                ->where('type', OfferTypeEnum::GIFT->value)
                ->where('status', true)->get() as $giftOfferData
        ) {
            $triggerData = json_decode($giftOfferData->trigger_data, true);

            $this->offerMeters[$giftOfferData->allowance_signature] = [
                'offer_id' => $giftOfferData->id,
                'label'    => $giftOfferData->name,
                'is_gift'  => true,
                'metadata' => [
                    'current' => $order->gross_amount,
                    'target'  => Arr::get($triggerData, 'min_order_amount', 0),

                ]
            ];
        }
    }

    private function setEnabledOffers(Order $order): void
    {
        $enabledOffers = [];


        foreach (
            DB::table('offers')
                ->select(['id', 'type', 'trigger_data', 'allowance_signature', 'name'])
                ->where('customer_id', $order->customer_id)
                ->where('status', true)
                ->get() as $customerExclusiveOfferData
        ) {
            if ($customerExclusiveOfferData->type == OfferTypeEnum::CUSTOMER_ANY_ORDER->value) {
                $enabledOffers[$customerExclusiveOfferData->allowance_signature] = [
                    'offer_id'    => $customerExclusiveOfferData->id,
                    'offer_label' => $customerExclusiveOfferData->name,
                ];
            } elseif ($customerExclusiveOfferData->type == OfferTypeEnum::CUSTOMER_AMOUNT_ORDERED->value) {
                $triggerData = json_decode($customerExclusiveOfferData->trigger_data, true);

                if ($order->gross_amount >= Arr::get($triggerData, 'min_order_amount', 0)) {
                    $enabledOffers[$customerExclusiveOfferData->allowance_signature] = [
                        'offer_id'    => $customerExclusiveOfferData->id,
                        'offer_label' => $customerExclusiveOfferData->name,
                    ];
                }
            }
        }


        if ($order->offer_voucher_id) {
            $voucherData = DB::table('offers')
                ->select(['id', 'type', 'trigger_data', 'allowance_signature', 'name', 'trigger_type', 'trigger_id'])
                ->where('shop_id', $order->shop_id)
                ->where('status', true)
                ->where('id', $order->offer_voucher_id)
                ->first();

            if ($voucherData) {
                if ($voucherData->type == OfferTypeEnum::VOUCHER_ANY_ORDER->value) {
                    $enabledOffers[$voucherData->allowance_signature] = [
                        'offer_id'    => $voucherData->id,
                        'offer_label' => $voucherData->name,
                    ];
                } elseif ($voucherData->type == OfferTypeEnum::VOUCHER_AMOUNT_ORDERED->value) {
                    $triggerData = json_decode($voucherData->trigger_data, true);

                    if ($order->gross_amount >= Arr::get($triggerData, 'item_amount', 0)) {
                        $enabledOffers[$voucherData->allowance_signature] = [
                            'offer_id'    => $voucherData->id,
                            'offer_label' => $voucherData->name,
                        ];
                    }
                }
            }
        }


        $offersData = DB::table('offers')
            ->select(['id', 'type', 'trigger_data', 'allowance_signature', 'name', 'trigger_type', 'trigger_id'])
            ->where('shop_id', $order->shop_id)
            ->where('status', true)
            ->whereIn('trigger_type', [
                'Customer',
                'ProductCategory',
                'ShopAiku'//todo: after migration, you can change to Shop , after all aurora type=Shop are terminated
            ])->get();
        foreach ($offersData as $offerData) {
            if ($offerData->type == 'Amount AND Order Number') {
                list($passAmount, $passOrderNumber, $metadata) = $this->checkAmountAndOrderNumber($order, $offerData);
                if ($passAmount && $passOrderNumber) {
                    $enabledOffers[$offerData->allowance_signature] = [
                        'offer_id'    => $offerData->id,
                        'offer_label' => $offerData->name,
                        'sub_trigger' => 'fob',
                    ];
                }
                if ($passOrderNumber) {
                    $this->offerMeters[$offerData->allowance_signature] = [
                        'offer_id' => $offerData->id,
                        'label'    => $offerData->name,
                        'is_gift'  => false,
                        'metadata' => $metadata,
                    ];
                }
            } elseif ($offerData->type == 'Shop Ordered') {
                $enabledOffers[$offerData->allowance_signature] = [
                    'offer_id'    => $offerData->id,
                    'offer_label' => $offerData->name
                ];
            } elseif ($offerData->type == 'Department Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'departments_ids', []))) {
                    $enabledOffers[$offerData->allowance_signature] = [
                        'offer_id'    => $offerData->id,
                        'offer_label' => $offerData->name
                    ];
                }
            } elseif ($offerData->type == 'Subdepartment Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'sub_departments_ids', []))) {
                    $enabledOffers[$offerData->allowance_signature] = [
                        'offer_id'    => $offerData->id,
                        'offer_label' => $offerData->name
                    ];
                }
            } elseif ($offerData->type == 'Category Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'family_ids', []))) {
                    $enabledOffers[$offerData->allowance_signature] = [
                        'offer_id'    => $offerData->id,
                        'offer_label' => $offerData->name
                    ];
                }
            } elseif ($offerData->type == 'Department Quantity Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'departments_ids', []))) {
                    $triggerData = json_decode($offerData->trigger_data, true);

                    if (Arr::get($order->categories_data, "department.$offerData->trigger_id.quantity", 0) >= Arr::get($triggerData, 'item_quantity')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                        ];
                    }
                }
            } elseif ($offerData->type == 'Subdepartment Quantity Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'sub_departments_ids', []))) {
                    $triggerData = json_decode($offerData->trigger_data, true);

                    if (Arr::get($order->categories_data, "sub_department.$offerData->trigger_id.quantity", 0) >= Arr::get($triggerData, 'item_quantity')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                        ];
                    }
                }
            } elseif ($offerData->type == 'Category Quantity Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'family_ids', []))) {
                    $triggerData = json_decode($offerData->trigger_data, true);

                    if (Arr::get($order->categories_data, "family.$offerData->trigger_id.quantity", 0) >= Arr::get($triggerData, 'item_quantity')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                        ];
                    }
                }
            } elseif ($offerData->type == 'Shop Amount Ordered') {
                $triggerData = json_decode($offerData->trigger_data, true);

                if ($order->gross_amount >= Arr::get($triggerData, 'item_amount')) {
                    $enabledOffers[$offerData->allowance_signature] = [
                        'offer_id'    => $offerData->id,
                        'offer_label' => $offerData->name,
                    ];
                }
            } elseif ($offerData->type == 'Department Amount Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'departments_ids', []))) {
                    $triggerData = json_decode($offerData->trigger_data, true);

                    if (Arr::get($order->categories_data, "department.$offerData->trigger_id.net_amount", 0) >= Arr::get($triggerData, 'item_amount')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                        ];
                    }
                }
            } elseif ($offerData->type == 'Subdepartment Amount Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'sub_departments_ids', []))) {
                    $triggerData = json_decode($offerData->trigger_data, true);

                    if (Arr::get($order->categories_data, "sub_department.$offerData->trigger_id.net_amount", 0) >= Arr::get($triggerData, 'item_amount')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                        ];
                    }
                }
            } elseif ($offerData->type == 'Category Amount Ordered') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'family_ids', []))) {
                    $triggerData = json_decode($offerData->trigger_data, true);

                    if (Arr::get($order->categories_data, "family.$offerData->trigger_id.net_amount", 0) >= Arr::get($triggerData, 'item_amount')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                        ];
                    }
                }
            } elseif ($offerData->type == 'Category Quantity Ordered Order Interval') {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'family_ids', []))) {
                    $amnestyOfferId = $this->getGrAmnestyOfferId($order);
                    if ($amnestyOfferId) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'             => $offerData->id,
                            'offer_label'          => $offerData->name,
                            'sub_trigger'          => 'a',
                            'sub_trigger_offer_id' => $amnestyOfferId,
                        ];
                        continue;
                    }


                    $daysSinceLastInvoiced = $this->getDaysSinceLastInvoiced($order);
                    $triggerData           = json_decode($offerData->trigger_data, true);


                    if ($daysSinceLastInvoiced <= Arr::get($triggerData, 'interval')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                            'sub_trigger' => 'i'
                        ];
                        continue;
                    }


                    if (Arr::get($order->categories_data, "family.$offerData->trigger_id.quantity", 0) >= Arr::get($triggerData, 'item_quantity')) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                            'sub_trigger' => 'q'
                        ];
                    }
                }
            }
        }


        $this->enabledOffers = $enabledOffers;
    }


    public function getGrAmnestyOfferId(Order $order): null|int
    {
        if ($this->isGrAmnestyOfferIdSet) {
            return $this->grAmnestyOfferId;
        }

        $isGrAmnestyOfferId          = Cache::remember("gr_amnesty_offer_id_$order->shop_id", now()->addHour(), function () use ($order) {
            return Arr::get($order->shop->offers_data, "gr.amnesty_offer_id");
        });
        $this->isGrAmnestyOfferIdSet = true;
        $this->grAmnestyOfferId      = $isGrAmnestyOfferId;

        return $isGrAmnestyOfferId;
    }

    public function getDaysSinceLastInvoiced(Order $order): int
    {

        $customer = $order->customer;
        if (!$customer) {
            return 10000;
        }

        if ($this->isLastInvoicedSet) {
            return $this->daysSinceLastInvoiced ?? 10000;
        }

        $lastInvoiced            = Cache::remember("customer_last_invoiced_at_$customer->id", now()->addDay(), function () use ($customer) {
            return $customer->last_invoiced_at;
        });
        $this->isLastInvoicedSet = true;
        // Explicitly cast to int to prevent PHP 8.4+ precision loss warnings
        $this->daysSinceLastInvoiced = $lastInvoiced ? (int)-now()->diffInDays($lastInvoiced) : null;

        return $this->daysSinceLastInvoiced ?? 10000;
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

        if (!$allowanceData) {
            return;
        }

        if ($allowanceData->target_type == 'all_products_in_order') {
            $this->processAllowanceAllProductsInOrder($offerData, $allowanceData);
        } elseif ($allowanceData->target_type == 'all_products_in_product_category') {
            $this->processAllowanceAllProductsInProductCategory($offerData, $allowanceData);
        } elseif ($allowanceData->target_type == 'all_products_in_department') {
            $this->processAllowanceAllProductsInDepartment($offerData, $allowanceData);
        }
    }

    public function processAllowanceAllProductsInProductCategory(array $offerData, $allowanceData): void
    {
        $this->applyPercentageDiscount($offerData, $allowanceData, 'family');
    }

    public function processAllowanceAllProductsInDepartment(array $offerData, $allowanceData): void
    {
        $this->applyPercentageDiscount($offerData, $allowanceData, 'department');
    }

    public function processAllowanceAllProductsInOrder(array $offerData, $allowanceData): void
    {
        $this->applyPercentageDiscount($offerData, $allowanceData);
    }

    private function applyPercentageDiscount(array $offerData, $allowanceData, ?string $filterBy = null): void
    {
        $allowanceOpsData = json_decode($allowanceData->data, true) ?? [];
        $percentageOff    = isset($allowanceOpsData['percentage_off']) ? (float)$allowanceOpsData['percentage_off'] : 0.0;

        // Clamp to [0,1]
        $percentageOff = max(0.0, min(1.0, $percentageOff));

        if ($percentageOff <= 0) {
            return;
        }

        foreach ($this->transactions as $transaction) {
            if ($filterBy == 'family' && $allowanceOpsData['category_id'] != $transaction->family_id) {
                continue;
            }
            if ($filterBy == 'department' && $allowanceOpsData['category_id'] != $transaction->department_id) {
                continue;
            }

            $current = property_exists($transaction, 'discounted_percentage') ? $transaction->discounted_percentage : null;

            // Apply only if undefined or lower than the new percentage
            if ($current === null || (is_numeric($current) && (float)$current < $percentageOff)) {
                $this->applyOfferToTransaction(
                    $transaction,
                    $percentageOff,
                    $offerData['offer_label'],
                    $allowanceData,
                    Arr::get($offerData, 'sub_trigger'),
                    Arr::get($offerData, 'sub_trigger_offer_id')
                );
            }
        }
    }

    private function applyDiscretionaryOffer(object $transaction, float $percentageOff, string $label, OfferAllowance $allowance): void
    {
        $this->applyOfferToTransaction($transaction, $percentageOff, $label, $allowance);
    }

    private function applyOfferToTransaction(
        object $transaction,
        float $percentageOff,
        string $label,
        object $allowance,
        ?string $subTrigger = null,
        ?int $subTriggerOfferId = null
    ): void {
        $discountedAmount = round((float)$transaction->gross_amount * $percentageOff, 2);

        $transaction->with_offer            = true;
        $transaction->discounted_percentage = $percentageOff;
        $transaction->net_amount            = (float)$transaction->gross_amount - $discountedAmount;
        $transaction->discounted_amount     = $discountedAmount;
        $transaction->offer_id              = $allowance->offer_id;
        $transaction->offer_campaign_id     = $allowance->offer_campaign_id;
        $transaction->offer_allowance_id    = $allowance->id;
        $transaction->offer_label           = $label;
        $transaction->allowance_type        = 'percentage';
        $transaction->sub_trigger           = $subTrigger;
        $transaction->sub_trigger_offer_id  = $subTriggerOfferId;
    }

    private function updateTransactionDiscount(Order $order, object $transaction, float $discountedPercentage, float $discountedAmount, array $offersData): void
    {
        DB::table('transactions')->where('id', $transaction->id)
            ->update([
                'gross_amount'            => $transaction->gross_amount,
                'net_amount'              => (float)$transaction->gross_amount - $discountedAmount,
                'current_discount_factor' => 1 - $discountedPercentage,
                'offers_data'             => $offersData,
            ]);

        DB::table('transaction_has_offer_allowances')->insert([
            'order_id'              => $order->id,
            'transaction_id'        => $transaction->id,
            'model_type'            => $transaction->model_type,
            'model_id'              => $transaction->model_id,
            'offer_campaign_id'     => Arr::get($offersData, 'o.oc'),
            'offer_id'              => Arr::get($offersData, 'o.o'),
            'offer_allowance_id'    => Arr::get($offersData, 'o.oa'),
            'discounted_amount'     => $discountedAmount,
            'discounted_percentage' => $discountedPercentage,
            'free_items_value'      => Arr::get($offersData, 'o.f', 0),
            'number_of_free_items'  => Arr::get($offersData, 'o.nf', 0),
            'created_at'            => now(),
            'updated_at'            => now(),
            'data'                  => '{}',
        ]);
    }

    public function processDiscretionaryOffers(Order $order): void
    {
        if (count($order->discretionary_offers_data) == 0) {
            return;
        }

        $discretionaryOfferAllowance = OfferAllowance::where('shop_id', $order->shop_id)->where('is_discretionary', true)->first();

        foreach ($order->discretionary_offers_data as $transactionId => $discretionaryOffer) {
            $percentageOff = max(0.0, min(1.0, $discretionaryOffer['percentage']));
            $label         = $discretionaryOffer['label'];


            $transaction = $this->transactions->get($transactionId);


            if (!$transaction) {
                continue;
            }

            $hasOffer = property_exists($transaction, 'with_offer') && $transaction->with_offer;


            if ($hasOffer) {
                $current = property_exists($transaction, 'discounted_percentage') ? $transaction->discounted_percentage : null;
                if ((float)$current <= $percentageOff) {
                    $this->applyDiscretionaryOffer($transaction, $percentageOff, $label, $discretionaryOfferAllowance);
                }
            } else {
                $this->applyDiscretionaryOffer($transaction, $percentageOff, $label, $discretionaryOfferAllowance);
            }
        }
    }

}

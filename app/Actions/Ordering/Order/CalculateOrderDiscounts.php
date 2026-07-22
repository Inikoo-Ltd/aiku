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
    private float $amountOff = 0.0;
    private bool $isLastInvoicedSet = false;
    private bool $isGrAmnestyOfferIdSet = false;
    private int|null $daysSinceLastInvoiced = null;
    private int|null $grAmnestyOfferId = null;

    public function getJobUniqueId(Order $order): string
    {
        return $order->id;
    }

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        if (in_array($order->state, [
            OrderStateEnum::CANCELLED,
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
        ])) {
            return $order;
        }

        $this->transactions = collect();
        $this->amountOff    = 0.0;

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

        DB::transaction(function () use ($order) {
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

            $offerAllowancePivots = [];
            foreach ($this->transactions as $transaction) {
                if (property_exists($transaction, 'with_offer')) {
                    $offerAllowancePivots[] = $this->updateTransactionDiscount(
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
            if ($offerAllowancePivots !== []) {
                DB::table('transaction_has_offer_allowances')->insert($offerAllowancePivots);
            }

            if ($order->state != OrderStateEnum::CREATING) {
                $this->regenerateSubmittedTransactionDiscounts($order);
            }
        });

        if ((float)$order->amount_off != $this->amountOff) {
            $order->update(['amount_off' => $this->amountOff]);
        }

        CalculateOrderTotalAmounts::run(order: $order, calculateShipping: true, calculateDiscounts: false);

        $this->getGiftsMeters($order);
        $this->getVoucherMeterNonPercentage($order);


        $order->update(
            [
                'offer_meters' => $this->offerMeters
            ]
        );


        return $order;
    }

    public function regenerateSubmittedTransactionDiscounts(Order $order): void
    {
        $offerAllowancePivots = [];

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

            $offerAllowancePivots[] = $this->updateTransactionDiscount(
                $order,
                $transactionWithSubmittedDiscount,
                $percentageOff,
                $discountedAmount,
                $transactionWithSubmittedDiscount->submitted_offers_data
            );
        }

        if ($offerAllowancePivots !== []) {
            DB::table('transaction_has_offer_allowances')->insert($offerAllowancePivots);
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

    public function getVoucherMeterNonPercentage(Order $order): void
    {
        if (!$order->offer_voucher_id) {
            return;
        }

        $voucherData = DB::table('offers')
            ->select(['id', 'trigger_data', 'allowance_signature', 'name', 'allowance_type'])
            ->where('shop_id', $order->shop_id)
            ->where('status', true)
            ->whereIn('allowance_type', ['gift', 'discounted_shipping'])
            ->where('id', $order->offer_voucher_id)
            ->first();

        if (!$voucherData) {
            return;
        }

        $triggerData = json_decode($voucherData->trigger_data, true);

        $this->offerMeters[$voucherData->allowance_signature] = [
            'offer_id' => $voucherData->id,
            'label'    => $voucherData->name,
            'is_gift'  => $voucherData->allowance_type === 'gift',
            'metadata' => [
                'current' => $order->gross_amount,
                'target'  => Arr::get($triggerData, 'item_amount', 0),
            ]
        ];
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
                ->whereIn('allowance_type', ['percentage_off', 'amount_off'])
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
                'Product',
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
            } elseif (in_array($offerData->type, [
                OfferTypeEnum::PRODUCT_FOR_EVERY_QUANTITY_ORDERED->value,
                OfferTypeEnum::PRODUCT_QUANTITY_ORDERED->value,
                OfferTypeEnum::PRODUCT_AMOUNT_ORDERED->value,
            ])) {
                $enabledOffers[$offerData->allowance_signature] = [
                    'offer_id'    => $offerData->id,
                    'offer_label' => $offerData->name,
                ];
            } elseif ($offerData->type == OfferTypeEnum::CATEGORY_FOR_EVERY_QUANTITY_ORDERED->value) {
                if (in_array($offerData->trigger_id, Arr::get($order->categories_data, 'family_ids', []))) {
                    $triggerData     = json_decode($offerData->trigger_data, true);
                    $familyQuantity  = Arr::get($order->categories_data, "family.$offerData->trigger_id.quantity", 0);
                    $triggerQuantity = Arr::get($triggerData, 'item_quantity', 0);

                    if ($triggerQuantity > 0 && $familyQuantity >= $triggerQuantity) {
                        $enabledOffers[$offerData->allowance_signature] = [
                            'offer_id'    => $offerData->id,
                            'offer_label' => $offerData->name,
                        ];
                    }

                    $this->offerMeters[$offerData->allowance_signature] = [
                        'offer_id' => $offerData->id,
                        'label'    => $offerData->name,
                        'is_gift'  => false,
                        'metadata' => [
                            'current' => $familyQuantity,
                            'target'  => $triggerQuantity,
                        ]
                    ];
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
        $orderNumber = Arr::get($triggerData, 'order_number');

        if ($order->gross_amount >= Arr::get($triggerData, 'min_amount', 0)) {
            $passAmount = true;
        }

        $numberOrders = DB::table('orders')->where('customer_id', $order->customer_id)
            ->whereNotIn('state', [
                OrderStateEnum::CANCELLED->value,
                OrderStateEnum::CREATING->value,
            ])->count();

        if ($orderNumber !== null && $numberOrders == ($orderNumber - 1)) {
            $passOrderNumber = true;

            $metadata = [
                'current' => $order->gross_amount,
                'target'  => Arr::get($triggerData, 'min_amount', 0),
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
        if (empty($this->enabledOffers)) {
            return;
        }

        $allowances = DB::table('offer_allowances')
            ->select(['target_type', 'type', 'data', 'offer_id', 'id', 'offer_campaign_id'])
            ->whereIn('offer_id', array_column($this->enabledOffers, 'offer_id'))
            ->orderBy('id')
            ->get()
            ->unique('offer_id')
            ->keyBy('offer_id');

        foreach ($this->enabledOffers as $offerData) {
            $allowanceData = $allowances->get($offerData['offer_id']);

            if (!$allowanceData) {
                continue;
            }

            $this->processAllowance($offerData, $allowanceData);
        }
    }

    public function processAllowance(array $offerData, object $allowanceData): void
    {
        if ($allowanceData->type == 'amount_off') {
            $this->processAllowanceAmountOff($allowanceData);

            return;
        }

        if ($allowanceData->target_type == 'all_products_in_order') {
            $this->processAllowanceAllProductsInOrder($offerData, $allowanceData);
        } elseif ($allowanceData->target_type == 'all_products_in_product_category') {
            $this->processAllowanceAllProductsInProductCategory($offerData, $allowanceData);
        } elseif ($allowanceData->target_type == 'all_products_in_department') {
            $this->processAllowanceAllProductsInDepartment($offerData, $allowanceData);
        } elseif ($allowanceData->target_type == 'all_products_in_sub_department') {
            $this->applyPercentageDiscount($offerData, $allowanceData, 'sub_department');
        } elseif ($allowanceData->target_type == 'all_products_in_collection') {
            $this->applyPercentageDiscount($offerData, $allowanceData, 'collection');
        } elseif ($allowanceData->target_type == 'cheapest_products_in_product_category') {
            $this->processAllowanceFreeItems($offerData, $allowanceData);
        } elseif ($allowanceData->target_type == 'product' && $allowanceData->type == 'free_items') {
            $this->processAllowanceFreeItems($offerData, $allowanceData);
        } elseif ($allowanceData->target_type == 'product' && $allowanceData->type == 'percentage_off') {
            $this->processAllowanceProductPercentage($offerData, $allowanceData);
        }
    }

    public function processAllowanceProductPercentage(array $offerData, object $allowanceData): void
    {
        $allowanceOpsData = json_decode($allowanceData->data, true) ?? [];
        $productId        = Arr::get($allowanceOpsData, 'product_id');

        if (!$productId) {
            return;
        }

        $productTransactions = $this->transactions
            ->filter(fn ($transaction) => $transaction->model_id == $productId && $transaction->quantity_ordered > 0);

        if ($productTransactions->isEmpty()) {
            return;
        }

        if ($steps = Arr::get($allowanceOpsData, 'steps')) {
            $totalQuantity = (int)$productTransactions->sum('quantity_ordered');
            $percentageOff = 0.0;
            foreach (collect($steps)->sortBy('min_quantity') as $step) {
                if ($totalQuantity >= (int)Arr::get($step, 'min_quantity', PHP_INT_MAX)) {
                    $percentageOff = (float)Arr::get($step, 'percentage_off', 0);
                }
            }
        } else {
            $percentageOff = (float)Arr::get($allowanceOpsData, 'percentage_off', 0);

            $itemQuantity = (int)Arr::get($allowanceOpsData, 'item_quantity', 0);
            $itemAmount   = (float)Arr::get($allowanceOpsData, 'item_amount', 0);
            if ($itemQuantity > 0 && $productTransactions->sum('quantity_ordered') < $itemQuantity) {
                return;
            }
            if ($itemAmount > 0 && $productTransactions->sum('gross_amount') < $itemAmount) {
                return;
            }
        }

        $percentageOff = max(0.0, min(1.0, $percentageOff));
        if ($percentageOff <= 0) {
            return;
        }

        foreach ($productTransactions as $transaction) {
            $current = property_exists($transaction, 'discounted_percentage') ? $transaction->discounted_percentage : null;
            if ($current === null || (is_numeric($current) && (float)$current < $percentageOff)) {
                $this->applyOfferToTransaction(
                    $transaction,
                    $percentageOff,
                    $offerData['offer_label'],
                    $allowanceData
                );
            }
        }
    }

    public function processAllowanceFreeItems(array $offerData, object $allowanceData): void
    {
        $allowanceOpsData = json_decode($allowanceData->data, true) ?? [];
        $itemQuantity     = (int)Arr::get($allowanceOpsData, 'item_quantity', 0);
        $freeQuantity     = (int)Arr::get($allowanceOpsData, 'free_quantity', 0);
        $categoryId       = Arr::get($allowanceOpsData, 'category_id');
        $productId        = Arr::get($allowanceOpsData, 'product_id');

        if ($itemQuantity <= 0 || $freeQuantity <= 0 || (!$categoryId && !$productId)) {
            return;
        }

        $familyTransactions = $this->transactions
            ->filter(fn ($transaction) => ($productId ? $transaction->model_id == $productId : $transaction->family_id == $categoryId)
                && $transaction->quantity_ordered > 0 && $transaction->gross_amount > 0);

        $totalUnits = (int)$familyTransactions->sum('quantity_ordered');
        $freeUnits  = min(intdiv($totalUnits, $itemQuantity) * $freeQuantity, $totalUnits);

        if ($freeUnits <= 0) {
            return;
        }

        $sortedByUnitPrice = $familyTransactions->sortBy(fn ($transaction) => $transaction->gross_amount / $transaction->quantity_ordered)->values();

        foreach ($sortedByUnitPrice as $transaction) {
            if ($freeUnits <= 0) {
                break;
            }

            $takenUnits = (int)min($freeUnits, $transaction->quantity_ordered);
            $freeUnits  -= $takenUnits;

            $unitPrice        = $transaction->gross_amount / $transaction->quantity_ordered;
            $discountedAmount = round($unitPrice * $takenUnits, 2);
            $percentageOff    = $takenUnits / $transaction->quantity_ordered;

            $current = property_exists($transaction, 'discounted_percentage') ? (float)$transaction->discounted_percentage : null;
            if ($current !== null && $current >= $percentageOff) {
                continue;
            }

            $this->applyOfferToTransaction(
                $transaction,
                $percentageOff,
                $offerData['offer_label'],
                $allowanceData
            );

            $transaction->allowance_type       = 'free_items';
            $transaction->discounted_amount    = $discountedAmount;
            $transaction->net_amount           = (float)$transaction->gross_amount - $discountedAmount;
            $transaction->free_items_value     = $discountedAmount;
            $transaction->number_of_free_items = $takenUnits;
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

        $matchingTransactions = $this->getMatchingTransactions($allowanceOpsData, $filterBy);

        $itemAmount = (float)Arr::get($allowanceOpsData, 'item_amount', 0);
        if ($itemAmount > 0 && $matchingTransactions->sum('gross_amount') < $itemAmount) {
            return;
        }

        foreach ($matchingTransactions as $transaction) {
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

    private function getMatchingTransactions(array $allowanceOpsData, ?string $filterBy): \Illuminate\Support\Collection
    {
        $collectionProductIds = [];
        if ($filterBy == 'collection') {
            $collectionProductIds = DB::table('collection_has_models')
                ->where('collection_id', Arr::get($allowanceOpsData, 'collection_id'))
                ->where('model_type', 'Product')
                ->pluck('model_id')
                ->all();
            if ($collectionProductIds === []) {
                return collect();
            }
        }

        return $this->transactions->filter(
            fn ($transaction) => match ($filterBy) {
                'family' => Arr::get($allowanceOpsData, 'category_id') == $transaction->family_id,
                'department' => Arr::get($allowanceOpsData, 'category_id') == $transaction->department_id,
                'sub_department' => Arr::get($allowanceOpsData, 'category_id') == $transaction->sub_department_id,
                'collection' => in_array($transaction->model_id, $collectionProductIds),
                'product' => Arr::get($allowanceOpsData, 'product_id') == $transaction->model_id,
                default => true,
            }
        );
    }

    public function processAllowanceAmountOff(object $allowanceData): void
    {
        $allowanceOpsData = json_decode($allowanceData->data, true) ?? [];
        $amountOff        = (float)Arr::get($allowanceOpsData, 'amount_off', 0);

        if ($amountOff <= 0) {
            return;
        }

        $filterBy = match ($allowanceData->target_type) {
            'all_products_in_product_category' => 'family',
            'all_products_in_department' => 'department',
            'all_products_in_sub_department' => 'sub_department',
            'all_products_in_collection' => 'collection',
            'product' => 'product',
            default => null,
        };

        $matchingTransactions = $this->getMatchingTransactions($allowanceOpsData, $filterBy);
        if ($matchingTransactions->isEmpty()) {
            return;
        }

        $matchingGross = (float)$matchingTransactions->sum('gross_amount');

        $itemAmount = (float)Arr::get($allowanceOpsData, 'item_amount', 0);
        if ($itemAmount > 0 && $matchingGross < $itemAmount) {
            return;
        }

        $this->amountOff = min($amountOff, $matchingGross);
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

    /**
     * Updates the transaction row and returns the transaction_has_offer_allowances
     * row to be bulk inserted by the caller.
     *
     * @return array<string, mixed>
     */
    private function updateTransactionDiscount(Order $order, object $transaction, float $discountedPercentage, float $discountedAmount, array $offersData): array
    {
        DB::table('transactions')->where('id', $transaction->id)
            ->update([
                'gross_amount'            => $transaction->gross_amount,
                'net_amount'              => (float)$transaction->gross_amount - $discountedAmount,
                'current_discount_factor' => 1 - $discountedPercentage,
                'offers_data'             => $offersData,
            ]);

        return [
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
        ];
    }

    public function processDiscretionaryOffers(Order $order): void
    {
        if (count($order->discretionary_offers_data) == 0) {
            return;
        }

        $discretionaryOfferAllowance = OfferAllowance::where('shop_id', $order->shop_id)->where('is_discretionary', true)->first();

        if (!$discretionaryOfferAllowance) {
            return;
        }

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

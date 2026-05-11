<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Comms\Email\SendNewOrderEmailToCustomer;
use App\Actions\Comms\Email\SendNewOrderEmailToSubscribers;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTrafficSource;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateBasket;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SubmitOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    private Order $order;


    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        $oldState = $order->state;

        $modelData = [
            'state'          => OrderStateEnum::SUBMITTED,
            'status'         => OrderStatusEnum::PROCESSING,
            'internal_notes' => $order->customer->warehouse_internal_notes,
        ];

        $date = now();

        if ($order->state == OrderStateEnum::CREATING || $order->submitted_at == null) {
            data_set($modelData, 'submitted_at', $date);
        } else {
            throw ValidationException::withMessages(
                [
                    'order' => [
                        'favourite' => 'Order has been submitted and cannot be submitted again',
                    ]
                ]
            );
        }

        $this->processGrGift($order);

        $transactions = $order->transactions()->where('state', TransactionStateEnum::CREATING)->get();
        /** @var Transaction $transaction */
        if ($transactions->isNotEmpty()) {
            foreach ($transactions as $transaction) {
                $transactionData = ['state' => TransactionStateEnum::SUBMITTED];
                if ($transaction->submitted_at == null) {
                    data_set($transactionData, 'submitted_at', $date);
                    data_set($transactionData, 'status', TransactionStatusEnum::PROCESSING);
                    data_set($transactionData, 'submitted_quantity_ordered', $transaction->quantity_ordered);
                    data_set($transactionData, 'submitted_gross_amount', $transaction->gross_amount);
                    data_set($transactionData, 'submitted_net_amount', $transaction->net_amount);
                    data_set($transactionData, 'submitted_discount_factor', $transaction->current_discount_factor);
                }

                $transaction->update($transactionData);
            }
        }

        $this->update($order, $modelData);

        if ($order->shop->masterShop) {
            $order->shop->masterShop->orderingStats->update(
                [
                    'last_order_submitted_at' => now()
                ]
            );
        }


        if ($order->customer_client_id) {
            CustomerClientHydrateBasket::run($order->customer_client_id);
        } else {
            CustomerHydrateBasket::run($order->customer_id);
        }

        $this->orderHydrators($order);
        $this->orderHandlingHydrators($order, $oldState);
        $this->orderHandlingHydrators($order, OrderStateEnum::SUBMITTED);

        if (!in_array($order->salesChannel?->type, [
            SalesChannelTypeEnum::PHONE,
            SalesChannelTypeEnum::SHOWROOM,
            SalesChannelTypeEnum::EMAIL,
            SalesChannelTypeEnum::OTHER
        ])) {
            SendNewOrderEmailToSubscribers::dispatch($order->id);
            SendNewOrderEmailToCustomer::dispatch($order->id);
        }

        if ($order->pay_status == OrderPayStatusEnum::PAID || $order->to_be_paid_by == OrderToBePaidByEnum::CASH_ON_DELIVERY) {
            SendOrderToWarehouse::make()->action($order, []);
        }

        $customerSalesChannel = $order->customerSalesChannel;
        if ($customerSalesChannel) {
            CustomerSalesChannelsHydrateOrders::dispatch($customerSalesChannel);
        }

        CustomerHydrateTrafficSource::dispatch($order->customer_id);

        return $order;
    }


    public function processGrGift(Order $order): Order
    {
        $offersData = $order->shop->offers_data;

        $grGiftOffer   = null;
        $grGiftOfferId = Arr::get($offersData, 'gr.gifts_offer_id');
        if ($grGiftOfferId) {
            $grGiftOffer = Offer::find($grGiftOfferId);
        }

        $eligible = false;

        if ($grGiftOffer) {
            $minAmount = Arr::get($grGiftOffer->trigger_data, 'min_amount', 100000);
            if ($order->gross_amount >= $minAmount) {
                $eligible = true;
            }
        }

        $isGiftOptedOut = (bool) Arr::get($order->customer->settings, 'is_gift_opted_out', false);

        if ($grGiftOffer && $eligible && !$isGiftOptedOut) {
            $selectedGrGift = Arr::get($order->data, 'gr.selected_gift');
            if (!$selectedGrGift) {
                $grGiftsData = Arr::get($offersData, 'gr.gifts_products');
                if ($grGiftsData) {
                    foreach ($grGiftsData as $gift) {
                        if (Arr::get($gift, 'default', false)) {
                            $selectedGrGift = $gift['id'];
                            break;
                        }
                    }
                }
            }


            if ($selectedGrGift) {
                /** @var OfferAllowance $giftAllowance */
                $giftAllowance = $grGiftOffer->offerAllowances()->first();
                if ($giftAllowance) {
                    $selectedGrGiftProduct = Product::where('shop_id', $order->shop_id)->where('id', $selectedGrGift)->first();
                    if ($selectedGrGiftProduct) {
                        $grGiftTransaction = StoreTransaction::make()->action(
                            $order,
                            $selectedGrGiftProduct->currentHistoricProduct,
                            [
                                'quantity_ordered' => 0,
                                'quantity_bonus'   => 1,
                                'is_gift'          => true,
                            ]
                        );


                        $grGiftTransaction->update([
                            'offers_data' => [
                                'v' => 1,
                                'o' => [
                                    'oc' => $grGiftOffer->offer_campaign_id,
                                    'o'  => $grGiftOffer->id,
                                    'oa' => $giftAllowance->id,
                                    't'  => 'gift',
                                    'p'  => 0,
                                    'l'  => $grGiftOffer->name,
                                ]
                            ]
                        ]);


                        DB::table('transaction_has_offer_allowances')->insert([
                            'order_id'              => $order->id,
                            'transaction_id'        => $grGiftTransaction->id,
                            'model_type'            => $grGiftTransaction->model_type,
                            'model_id'              => $grGiftTransaction->model_id,
                            'offer_campaign_id'     => $grGiftOffer->offer_campaign_id,
                            'offer_id'              => $grGiftOffer->id,
                            'offer_allowance_id'    => $giftAllowance->id,
                            'discounted_amount'     => 0,
                            'discounted_percentage' => 0,
                            'is_gift'               => true,
                            'free_items_value'      => $selectedGrGiftProduct->price,
                            'number_of_free_items'  => 1,
                            'created_at'            => now(),
                            'updated_at'            => now(),
                            'data'                  => '{}'

                        ]);
                    }
                }
            }
        }

        return $order;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->order->state == OrderStateEnum::CREATING && !$this->order->transactions->count() && !$this->asAction) {
            $validator->errors()->add('state', __('Can not submit an order without any transactions'));
        } elseif ($this->order->state == OrderStateEnum::SUBMITTED) {
            $validator->errors()->add('state', __('Order is already submitted'));
        } elseif ($this->order->state == OrderStateEnum::PACKED || $this->order->state == OrderStateEnum::HANDLING) {
            $validator->errors()->add('state', __('Order is already been picked'));
        } elseif ($this->order->state == OrderStateEnum::FINALISED) {
            $validator->errors()->add('state', __('Order is already finalised'));
        } elseif ($this->order->state == OrderStateEnum::DISPATCHED) {
            $validator->errors()->add('state', __('Order is already dispatched'));
        } elseif ($this->order->state == OrderStateEnum::CANCELLED) {
            $validator->errors()->add('state', __('Order has been cancelled'));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}

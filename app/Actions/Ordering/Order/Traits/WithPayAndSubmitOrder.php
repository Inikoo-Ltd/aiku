<?php

/*
 * Author Louis Perez
 * Created on 01-07-2026-11h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\Order\Traits;

use App\Actions\Comms\Email\SendChannelOrderOnHoldEmail;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Order\WithOrderForbiddenCountryCheck;
use App\Actions\Retina\Dropshipping\Orders\PayOrderAsync;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Models\Ordering\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Sentry;
use Throwable;

trait WithPayAndSubmitOrder
{
    use WithOrderForbiddenCountryCheck;

    public function payAndSubmitOrder(Order $order)
    {
        /** A channel order whose line items all failed to resolve must stay in basket state,
         * visible and unpaid, instead of submitting empty. Packaging and inserts are applied by
         * StoreOrder before the line items arrive, so they are not evidence that any resolved:
         * counting them would submit and charge for a parcel with nothing in it. */
        if ($order->transactions()->whereNotIn('model_type', ['Packaging', 'Leaflet'])->count() === 0) {
            Sentry::captureMessage('Channel order '.$order->reference.' ('.$order->id.') has no transactions after import, submit skipped');

            return $order;
        }

        $isForbidden = $this->isForbidden($order);

        // If forbidden, do not allow payment. So that it stucks at submit order only, and not in warehouse
        if (!$isForbidden) {
            try {
                PayOrderAsync::run($order);
            } catch (Exception $e) {
                Sentry::captureException($e);
            }
        }

        try {
            $order = SubmitOrder::make()->action($order);
        } catch (Throwable $e) {
            $this->alertPaidOrderNotSubmitted($order, $e);

            throw $e;
        }

        $this->tellCustomerWeCouldNotTakePayment($order);

        return $order;
    }

    /**
     * A platform order is placed without the customer watching, so an order we cannot charge would
     * otherwise stop dead without anyone outside this building knowing (HELP-3116). Only channel
     * orders: on a manual order the customer is at the checkout and sees the failure themselves.
     */
    protected function tellCustomerWeCouldNotTakePayment(Order $order): void
    {
        if (!$order->isPlacedOnAChannel() || $order->refresh()->pay_status === OrderPayStatusEnum::PAID) {
            return;
        }

        SendChannelOrderOnHoldEmail::dispatch($order->id);
    }

    /**
     * The money is taken before the submit, so a submit that fails leaves a paid order sitting in
     * the basket where nobody sees it (HELP-3064). SubmitOrder refuses with a ValidationException,
     * which Laravel keeps off Sentry, so the alert has to be raised here by hand. Any money taken
     * counts, not only a fully paid order: the balance is spent before the cards are tried, so a
     * part paid basket is just as invisible and just as much the customer's money.
     */
    protected function alertPaidOrderNotSubmitted(Order $order, Throwable $e): void
    {
        $order->refresh();

        $amountTaken = round($order->payment_amount, 2);

        if ($amountTaken <= 0) {
            return;
        }

        $paidDescription = $order->pay_status == OrderPayStatusEnum::PAID
            ? 'was paid'
            : 'was part paid ('.$amountTaken.' of '.round($order->total_amount, 2).')';

        $message = 'Order '.$order->reference.' ('.$order->id.') '.$paidDescription.' and then failed to submit: '.$e->getMessage();

        Sentry::captureMessage($message);

        /** Queued, not posted here: the order is already paid and Sentry has been told, so a webhook
         * that is down or slow must never become the customer's error as well. */
        $webhookUrl = config('services.discord.webhook_url');
        if ($webhookUrl) {
            dispatch(function () use ($webhookUrl, $message) {
                Http::timeout(10)->post($webhookUrl, [
                    'content' => "💸 **Paid order not submitted** 💸\n".$message
                ]);
            })->afterCommit();
        }
    }
}

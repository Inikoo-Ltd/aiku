<?php

/*
 * Author Louis Perez
 * Created on 01-07-2026-11h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\Order\Traits;

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
         * visible and unpaid, instead of submitting empty. */
        if ($order->transactions()->count() === 0) {
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
            return SubmitOrder::make()->action($order);
        } catch (Throwable $e) {
            $this->alertPaidOrderNotSubmitted($order, $e);

            throw $e;
        }
    }

    /**
     * The money is taken before the submit, so a submit that fails leaves a paid order sitting in
     * the basket where nobody sees it (HELP-3064). SubmitOrder refuses with a ValidationException,
     * which Laravel keeps off Sentry, so the alert has to be raised here by hand.
     */
    protected function alertPaidOrderNotSubmitted(Order $order, Throwable $e): void
    {
        if ($order->refresh()->pay_status != OrderPayStatusEnum::PAID) {
            return;
        }

        $message = 'Order '.$order->reference.' ('.$order->id.') was paid and then failed to submit: '.$e->getMessage();

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

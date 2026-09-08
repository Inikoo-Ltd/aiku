<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentSuccess;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\TopUpPaymentSuccess;
use App\Actions\Accounting\WithCheckoutCom;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentGatewayLog;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class SweepStuckCheckoutComPaymentApiPoints
{
    use AsAction;
    use WithCheckoutCom;

    public string $commandSignature = 'payments:sweep_stuck_checkout_api_points {--stuck-minutes=30} {--max-age-hours=48}';

    /**
     * @throws \Throwable
     */
    public function handle(int $stuckMinutes = 30, int $maxAgeHours = 48): array
    {
        $stats = [
            'orders_checked'    => 0,
            'orders_recovered'  => 0,
            'top_ups_checked'   => 0,
            'top_ups_recovered' => 0,
        ];

        $windowStart = now()->subHours($maxAgeHours);
        $windowEnd   = now()->subMinutes($stuckMinutes);

        $this->sweepOrders($windowStart, $windowEnd, $stats);
        $this->sweepTopUps($windowStart, $windowEnd, $stats);

        $stats['stale_gateway_logs'] = $this->alertStaleGatewayLogs();

        return $stats;
    }

    /**
     * Watchdog for the whole webhook pipeline: aiku events should always reach a terminal state
     * within minutes, so anything unprocessed after 2 hours means processing is broken or an
     * event is stranded (e.g. MIT pending, which has no other backstop).
     */
    private function alertStaleGatewayLogs(): int
    {
        $staleCount = PaymentGatewayLog::where('origin', 'aiku')
            ->where('environment', app()->environment())
            ->where('state', '!=', PaymentGatewayLogStateEnum::PROCESSED)
            ->whereBetween('created_at', [now()->subDays(7), now()->subHours(2)])
            ->count();

        if ($staleCount > 0 && app()->isProduction()) {
            Sentry::captureMessage(
                $staleCount.' checkout.com webhook events are older than 2 hours and still unprocessed — payment processing may be broken'
            );
        }

        return $staleCount;
    }

    /**
     * @throws \Throwable
     */
    private function sweepOrders($windowStart, $windowEnd, array &$stats): void
    {
        $orderIds = OrderPaymentApiPoint::whereIn('state', [OrderPaymentApiPointStateEnum::IN_PROCESS, OrderPaymentApiPointStateEnum::FAILURE])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereNull('data->swept_at')
            ->distinct()
            ->pluck('order_id');

        foreach ($orderIds as $orderId) {
            try {
                $this->sweepOrder($orderId, $stats);
            } catch (\Throwable $e) {
                \Sentry\captureException($e);
            }
        }
    }

    /**
     * @throws \Throwable
     */
    private function sweepOrder(int $orderId, array &$stats): void
    {
        $stuckApiPoints = OrderPaymentApiPoint::where('order_id', $orderId)
            ->whereIn('state', [OrderPaymentApiPointStateEnum::IN_PROCESS, OrderPaymentApiPointStateEnum::FAILURE])
            ->whereNull('data->swept_at')
            ->orderByDesc('id')
            ->get();

        $order = Order::find($orderId);
        if (!$order || $order->state != OrderStateEnum::CREATING) {
            $this->markSwept($stuckApiPoints);

            return;
        }

        $paymentAccountShop = null;
        foreach ($stuckApiPoints as $stuckApiPoint) {
            if ($paymentAccountShopId = Arr::get($stuckApiPoint->data, 'payment_methods.checkout')) {
                $paymentAccountShop = PaymentAccountShop::find($paymentAccountShopId);
                break;
            }
        }

        if (!$paymentAccountShop) {
            $this->markSwept($stuckApiPoints);

            return;
        }

        $stats['orders_checked']++;

        $checkoutComPayments = $this->getCheckOutPaymentsByReference($paymentAccountShop, $order->reference);
        if (Arr::get($checkoutComPayments, 'error')) {
            return;
        }

        $hasInFlightPayment = false;

        foreach (Arr::get($checkoutComPayments, 'data', []) as $checkoutComPayment) {
            /** Order references are only unique per shop and checkout.com credentials are shared
             * across shops, so a payment is only trusted when its session metadata points at an
             * api point of this very order */
            $orderPaymentApiPoint = OrderPaymentApiPoint::find(Arr::get($checkoutComPayment, 'metadata.api_point_id'));
            if (!$orderPaymentApiPoint || $orderPaymentApiPoint->order_id != $order->id) {
                continue;
            }

            $status = Arr::get($checkoutComPayment, 'status');

            if ($status == 'Partially Captured') {
                Sentry::captureMessage(
                    'Partially captured checkout.com payment '.Arr::get($checkoutComPayment, 'id').' found for stuck order '.$order->id.' — needs manual review'
                );
                continue;
            }

            if ($status == 'Authorized' || in_array($status, self::CHECKOUT_COM_PENDING_STATUSES)) {
                $hasInFlightPayment = true;
                continue;
            }

            if (in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
                CheckoutComOrderPaymentSuccess::make()->processSuccessfulPayment($orderPaymentApiPoint, $paymentAccountShop, $checkoutComPayment);

                $stats['orders_recovered']++;
                $hasInFlightPayment = false;
                break;
            }
        }

        if ($hasInFlightPayment) {
            return;
        }

        $this->markSwept($stuckApiPoints);
    }

    /**
     * @throws \Throwable
     */
    private function sweepTopUps($windowStart, $windowEnd, array &$stats): void
    {
        $stuckApiPoints = TopUpPaymentApiPoint::whereIn('state', [TopUpPaymentApiPointStateEnum::IN_PROCESS, TopUpPaymentApiPointStateEnum::FAILURE])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereNull('data->swept_at')
            ->get();

        foreach ($stuckApiPoints as $stuckApiPoint) {
            try {
                $this->sweepTopUp($stuckApiPoint, $stats);
            } catch (\Throwable $e) {
                \Sentry\captureException($e);
            }
        }
    }

    /**
     * @throws \Throwable
     */
    private function sweepTopUp(TopUpPaymentApiPoint $stuckApiPoint, array &$stats): void
    {
        $paymentAccountShop = PaymentAccountShop::find(Arr::get($stuckApiPoint->data, 'payment_account_shop_id.checkout'));
        if (!$paymentAccountShop) {
            $this->markSwept(collect([$stuckApiPoint]));

            return;
        }

        $stats['top_ups_checked']++;

        $checkoutComPayments = $this->getCheckOutPaymentsByReference($paymentAccountShop, $stuckApiPoint->ulid);
        if (Arr::get($checkoutComPayments, 'error')) {
            return;
        }

        $hasInFlightPayment = false;

        foreach (Arr::get($checkoutComPayments, 'data', []) as $checkoutComPayment) {
            $status = Arr::get($checkoutComPayment, 'status');

            if ($status == 'Partially Captured') {
                Sentry::captureMessage(
                    'Partially captured checkout.com payment '.Arr::get($checkoutComPayment, 'id').' found for stuck top up api point '.$stuckApiPoint->id.' — needs manual review'
                );
                continue;
            }

            if ($status == 'Authorized' || in_array($status, self::CHECKOUT_COM_PENDING_STATUSES)) {
                $hasInFlightPayment = true;
                continue;
            }

            if (in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
                TopUpPaymentSuccess::make()->processSuccess($checkoutComPayment, $stuckApiPoint, $paymentAccountShop);

                $stats['top_ups_recovered']++;
                $hasInFlightPayment = false;
                break;
            }
        }

        if ($hasInFlightPayment) {
            return;
        }

        $this->markSwept(collect([$stuckApiPoint]));
    }

    private function markSwept($apiPoints): void
    {
        foreach ($apiPoints as $apiPoint) {
            $apiPoint->update(['data->swept_at' => now()->toIso8601String()]);
        }
    }

    public function asCommand(Command $command): int
    {
        $stats = $this->handle(
            (int)$command->option('stuck-minutes'),
            (int)$command->option('max-age-hours')
        );

        $command->info(
            'Orders checked: '.$stats['orders_checked'].', recovered: '.$stats['orders_recovered']
            .' | Top ups checked: '.$stats['top_ups_checked'].', recovered: '.$stats['top_ups_recovered']
            .' | Stale gateway logs: '.$stats['stale_gateway_logs']
        );

        return 0;
    }

}

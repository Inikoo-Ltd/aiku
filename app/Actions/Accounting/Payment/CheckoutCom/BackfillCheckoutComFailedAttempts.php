<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentGatewayLog;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Historic failed checkout.com attempts were never stored as payments; their payloads survive
 * in the failed api points and in the webhook logs. Replays them through
 * StoreFailedCheckoutComPayment, which skips any reference already known (paid or failed).
 * Dry run unless --commit.
 */
class BackfillCheckoutComFailedAttempts
{
    use AsAction;

    public string $commandSignature = 'payments:backfill_checkout_com_failed_attempts {--commit}';

    private array $counts = ['stored' => 0, 'skipped' => 0, 'dated' => 0, 'unresolved' => 0];

    private bool $commit = false;

    public function asCommand(Command $command): int
    {
        $this->commit = (bool) $command->option('commit');

        OrderPaymentApiPoint::where('state', OrderPaymentApiPointStateEnum::FAILURE)
            ->whereNotNull('data->payment->id')
            ->orderBy('id')
            ->chunkById(500, function ($apiPoints) {
                foreach ($apiPoints as $apiPoint) {
                    $this->attempt(
                        $apiPoint->order?->customer,
                        PaymentAccountShop::find(Arr::get($apiPoint->data, 'payment_methods.checkout')),
                        Arr::get($apiPoint->data, 'payment'),
                        null,
                        ['type' => class_basename($apiPoint), 'id' => $apiPoint->id]
                    );
                }
            });

        TopUpPaymentApiPoint::where('state', TopUpPaymentApiPointStateEnum::FAILURE)
            ->whereNotNull('data->payment->id')
            ->orderBy('id')
            ->chunkById(500, function ($apiPoints) {
                foreach ($apiPoints as $apiPoint) {
                    $this->attempt(
                        $apiPoint->customer,
                        PaymentAccountShop::find(Arr::get($apiPoint->data, 'payment_account_shop_id.checkout')),
                        Arr::get($apiPoint->data, 'payment'),
                        null,
                        ['type' => class_basename($apiPoint), 'id' => $apiPoint->id]
                    );
                }
            });

        PaymentGatewayLog::whereIn('type', ProcessCheckoutComPaymentGatewayLog::FAILURE_EVENT_TYPES)
            ->whereNotNull('payload->data->id')
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                foreach ($logs as $log) {
                    [$customer, $paymentAccountShop, $apiPoint] = $this->resolveLog($log);
                    $this->attempt($customer, $paymentAccountShop, Arr::get($log->payload, 'data'), $log->type, $apiPoint);
                }
            });

        $command->info(($this->commit ? 'Stored' : 'Would store')." {$this->counts['stored']}, already known {$this->counts['skipped']} (dates repaired {$this->counts['dated']}), unresolved {$this->counts['unresolved']}");

        return 0;
    }

    private function attempt($customer, ?PaymentAccountShop $paymentAccountShop, ?array $checkoutComPayment, ?string $eventType, array $apiPoint = []): void
    {
        $reference = Arr::get($checkoutComPayment, 'id');
        if (!$customer || !$paymentAccountShop || !$reference) {
            $this->counts['unresolved']++;

            return;
        }

        $known = Payment::where('reference', $reference)->first();
        if ($known) {
            $this->counts['skipped']++;
            $this->repairDate($known, $checkoutComPayment);

            return;
        }

        $this->counts['stored']++;
        if ($this->commit) {
            StoreFailedCheckoutComPayment::run($customer, $paymentAccountShop, $checkoutComPayment, $eventType, $apiPoint);
        }
    }

    /** Failed attempts stored before the date was taken from the payload carry the day they were backfilled */
    private function repairDate(Payment $payment, array $checkoutComPayment): void
    {
        $date = StoreFailedCheckoutComPayment::dateOf($checkoutComPayment);
        if ($payment->status != PaymentStatusEnum::FAIL || !$date || $payment->date->isSameDay($date)) {
            return;
        }

        $this->counts['dated']++;
        if ($this->commit) {
            $payment->update(['date' => $date, 'created_at' => $date]);
        }
    }

    private function resolveLog(PaymentGatewayLog $log): array
    {
        $operation  = $log->operation ?: Arr::get($log->payload, 'data.metadata.operation');
        $apiPointId = $log->api_point_model_id ?: Arr::get($log->payload, 'data.metadata.api_point_id');

        if ($operation == 'order' && $apiPointId && $apiPoint = OrderPaymentApiPoint::find($apiPointId)) {
            return [$apiPoint->order?->customer, PaymentAccountShop::find(Arr::get($apiPoint->data, 'payment_methods.checkout')), ['type' => 'OrderPaymentApiPoint', 'id' => $apiPoint->id]];
        }

        if ($operation == 'top_up' && !$apiPointId && $ulid = Arr::get($log->payload, 'data.metadata.api_point_ulid')) {
            $apiPointId = TopUpPaymentApiPoint::where('ulid', $ulid)->value('id');
        }

        if ($operation == 'top_up' && $apiPointId && $apiPoint = TopUpPaymentApiPoint::find($apiPointId)) {
            return [$apiPoint->customer, PaymentAccountShop::find(Arr::get($apiPoint->data, 'payment_account_shop_id.checkout')), ['type' => 'TopUpPaymentApiPoint', 'id' => $apiPoint->id]];
        }

        $orderId = $log->order_id ?: Arr::get($log->payload, 'data.metadata.order_id');
        if ($operation == 'mit' && $orderId && $order = Order::find($orderId)) {
            $paymentAccountShop = $order->shop->paymentAccountShops()
                ->where('type', PaymentAccountTypeEnum::CHECKOUT)
                ->where('state', PaymentAccountShopStateEnum::ACTIVE)
                ->first();

            return [$order->customer, $paymentAccountShop, []];
        }

        /** Declines inside checkout.com Flow carry no api point, only the order reference the session was opened with */
        $orderReference = Arr::get($log->payload, 'data.reference');
        if ($orderReference) {
            $orders = Order::where('reference', $orderReference)->limit(2)->get();
            if ($orders->count() == 1) {
                $order              = $orders->first();
                $paymentAccountShop = $order->shop->paymentAccountShops()
                    ->where('type', PaymentAccountTypeEnum::CHECKOUT)
                    ->where('state', PaymentAccountShopStateEnum::ACTIVE)
                    ->first();

                return [$order->customer, $paymentAccountShop, []];
            }
        }

        return [null, null, []];
    }
}

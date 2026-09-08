<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 13:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Actions\Accounting\Payment\StorePayment;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records a checkout.com attempt that did not go through as a failed payment, so the payment
 * methods report sees declines and abandoned redirects, not only the money that arrived.
 * A failed payment is never attached to an order or invoice and sums to nothing anywhere;
 * the same checkout.com payment id (callback, redirect, webhook) is stored once.
 */
class StoreFailedCheckoutComPayment
{
    use AsAction;

    public function handle(Customer $customer, PaymentAccountShop $paymentAccountShop, array $checkoutComPayment, ?string $eventType = null, array $apiPoint = []): ?Payment
    {
        $reference = Arr::get($checkoutComPayment, 'id');
        if (!$reference || Payment::where('reference', $reference)->exists()) {
            return null;
        }

        $summary = Arr::get($checkoutComPayment, 'response_summary')
            ?? collect(Arr::get($checkoutComPayment, 'actions', []))->pluck('response_summary')->filter()->last();

        return StorePayment::make()->action($customer, $paymentAccountShop->paymentAccount, array_filter([
            'reference'               => $reference,
            'date'                    => self::dateOf($checkoutComPayment),
            'amount'                  => Arr::get($checkoutComPayment, 'amount', 0) / 100,
            'status'                  => PaymentStatusEnum::FAIL,
            'state'                   => self::stateFor(Arr::get($checkoutComPayment, 'status'), $eventType),
            'type'                    => PaymentTypeEnum::PAYMENT,
            'payment_account_shop_id' => $paymentAccountShop->id,
            'api_point_type'          => Arr::get($apiPoint, 'type'),
            'api_point_id'            => Arr::get($apiPoint, 'id'),
            'source'                  => Arr::get($checkoutComPayment, 'source'),
            'data'                    => [
                'checkout_com' => array_filter([
                    'status'           => Arr::get($checkoutComPayment, 'status'),
                    'event_type'       => $eventType,
                    'response_code'    => Arr::get($checkoutComPayment, 'response_code'),
                    'response_summary' => $summary,
                ]),
            ],
        ], fn ($value) => $value !== null));
    }

    public static function dateOf(array $checkoutComPayment): ?Carbon
    {
        $raw = Arr::get($checkoutComPayment, 'processed_on') ?? Arr::get($checkoutComPayment, 'requested_on');

        return $raw ? Carbon::parse($raw) : null;
    }

    public static function stateFor(?string $status, ?string $eventType): PaymentStateEnum
    {
        return match (true) {
            $status === 'Declined', $eventType === 'payment_declined', $eventType === 'payment_capture_declined', $eventType === 'payment_authentication_failed' => PaymentStateEnum::DECLINED,
            in_array($status, ['Voided', 'Cancelled', 'Canceled']), in_array($eventType, ['payment_canceled', 'payment_voided']) => PaymentStateEnum::CANCELLED,
            default => PaymentStateEnum::ERROR,
        };
    }
}

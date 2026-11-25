<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Payment\RefundPaymentByApi;
use App\Actions\Comms\Outbox\CreditBalanceNotification\ProcessCreditBalanceNotification;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Payment;
use App\Exceptions\CheckoutRefundOutcomeUnknown;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * Sends the money for items not shipped, which invoicing put in the customer credit, back to the Checkout.com card
 * payments of the order when the customer's setting, else the shop's, says so. Whatever cannot be refunded stays in the
 * credit, and the outcome is recorded once on the order.
 */
class RefundExcessToCard implements ShouldBeUnique
{
    use AsAction;
    use WithActionUpdate;

    public function getJobUniqueId(Order $order, float $amount, ?string $staffMode = null): string
    {
        return (string) $order->id;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function effectiveSetting(Order $order): array
    {
        $customerMode = Arr::get($order->customer?->settings, 'excess_refund_mode', 'shop');
        if (in_array($customerMode, ['refund', 'credit'], true)) {
            return [$customerMode, 'customer'];
        }

        return [Arr::get($order->shop->settings, 'excess_refund.mode') === 'refund' ? 'refund' : 'credit', 'shop'];
    }

    /**
     * @param  'refund'|'credit'|null  $staffMode  what staff chose for this order instead of the setting
     */
    public static function dispatchIfRefund(Order $order, float $amount, ?string $staffMode = null): void
    {
        if ($amount > 0 && ($staffMode ?? self::effectiveSetting($order)[0]) === 'refund') {
            static::dispatch($order, $amount, $staffMode)->afterCommit();
        }
    }

    /**
     * @param  'refund'|'credit'|null  $staffMode
     */
    public function handle(Order $order, float $amount, ?string $staffMode = null): void
    {
        $order->refresh();
        if (Arr::has($order->data, 'excess_refund')) {
            return;
        }

        [$mode, $source] = $staffMode ? [$staffMode, 'staff'] : self::effectiveSetting($order);
        if ($mode !== 'refund') {
            return;
        }

        $this->record($order, ['state' => 'pending', 'source' => $source, 'amount' => $amount, 'refunded' => 0, 'reason' => null, 'action_ids' => []]);

        $remaining = round($amount, 2);
        $refunded  = 0.0;
        $actionIds = [];
        $reason    = null;
        $unknown   = false;

        $cardPayments = $order->payments()
            ->where('payments.type', PaymentTypeEnum::PAYMENT)
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
            ->orderByDesc('payments.id')
            ->get()
            ->filter(fn (Payment $payment) => RefundPaymentByApi::canRefundByApi($payment->paymentAccount?->type?->value, $payment->reference));

        foreach ($cardPayments as $payment) {
            $balance = round((float) CreditTransaction::where('customer_id', $payment->customer_id)->sum('amount'), 2);
            $part    = round(min($remaining, RefundPaymentByApi::creditRefundableLeft($payment), $balance), 2);
            if ($part <= 0) {
                continue;
            }

            try {
                $refund = RefundPaymentByApi::make()->handle($payment, ['amount' => $part, 'from_credit' => true]);
            } catch (CheckoutRefundOutcomeUnknown $e) {
                $reason  = implode(' ', Arr::flatten($e->errors()));
                $unknown = true;
                break;
            } catch (ValidationException $e) {
                $reason = implode(' ', Arr::flatten($e->errors()));
                break;
            } catch (Throwable $e) {
                Sentry::captureException($e);
                $reason  = __('Unexpected error: :message', ['message' => $e->getMessage()]);
                $unknown = true;
                break;
            }

            $refunded  = round($refunded + $part, 2);
            $remaining = round($remaining - $part, 2);
            $actionIds[] = $refund->reference;
            if ($remaining <= 0) {
                break;
            }
        }

        $failed = $reason !== null;
        if (!$failed && $remaining > 0) {
            $reason = $refunded > 0 ? __('Part of the money was not paid by card') : __('Nothing of it was paid by a card that can be refunded');
        }

        $state = match (true) {
            $unknown                     => 'unknown',
            $failed                      => 'failed',
            $refunded > 0                => 'refunded',
            default                      => 'kept_as_credit',
        };

        if (in_array($state, ['failed', 'unknown'], true)) {
            Sentry::captureMessage("Refund to card of the money for items not shipped in order $order->reference $state: $reason");
        }

        /** A cancel held back its credit email for the money meant for the card; none of it got there, so the customer is told it is in their balance */
        if (in_array($state, ['failed', 'kept_as_credit'], true) && $refunded == 0 && $order->state === OrderStateEnum::CANCELLED && $order->customer) {
            ProcessCreditBalanceNotification::run($order->customer);
        }

        $this->record($order, [
            'state'      => $state,
            'source'     => $source,
            'amount'     => round($amount, 2),
            'refunded'   => $refunded,
            'reason'     => $reason,
            'action_ids' => $actionIds,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function record(Order $order, array $record): void
    {
        $order->refresh();
        $data                  = $order->data ?? [];
        $data['excess_refund'] = $record;
        $this->update($order, ['data' => $data]);
    }

    public function asJob(Order $order, float $amount, ?string $staffMode = null): void
    {
        Nightwatch::dontSample();

        $this->handle($order, $amount, $staffMode);
    }
}

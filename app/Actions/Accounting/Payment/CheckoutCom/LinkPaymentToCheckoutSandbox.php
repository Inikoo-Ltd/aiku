<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Actions\Accounting\Payment\RefundPaymentByApi;
use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\TopUp\StoreTopUp;
use App\Actions\Ordering\Order\PayOrder;
use App\Enums\Accounting\Payment\PaymentClassEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\Payment;
use Checkout\CheckoutApiException;
use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\Payments\Request\PaymentRequest;
use Checkout\Payments\Request\Source\RequestCardSource;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Local QA of refunds by API: local payments carry production Checkout.com ids that the sandbox does not know, so
 * this pays the same amount in the sandbox with the published test card and points the local payment at it
 * (keeping the old reference to put back). Never runs in production.
 */
class LinkPaymentToCheckoutSandbox
{
    use AsAction;

    public const array CHANNEL_BY_CURRENCY = [
        'GBP' => 'pc_edxuk74b2s5ufklugpanbx4bzq',
    ];

    public string $commandSignature = 'checkout:sandbox-payment {payment? : local payment id to link to a new sandbox payment} {--suggest : list one local payment per refund case} {--top-up= : customer id: make a new card top up into their credit} {--pay-order= : order id: pay it by card} {--amount= : amount for --top-up or --pay-order} {--restore : put back the reference the payment had before} {--channel= : sandbox processing channel, default by currency}';

    public string $commandDescription = 'Local QA only: link a local Checkout.com payment to a fresh sandbox payment so it can be refunded by API';

    public function handle(Payment $payment, ?string $channel = null): string
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sandbox payments are for local QA only');
        }
        if ($payment->paymentAccount->type !== PaymentAccountTypeEnum::CHECKOUT || $payment->type !== PaymentTypeEnum::PAYMENT) {
            throw new RuntimeException('Only Checkout.com payments (not refunds) can be linked to the sandbox');
        }

        $sandboxId = $this->payInSandbox((float) $payment->amount, $payment->currency->code, $channel, 'aiku-local-qa-'.$payment->id)['id'];

        $data = $payment->data ?? [];
        data_set($data, 'sandbox_original_reference', Arr::get($data, 'sandbox_original_reference', $payment->reference));
        $payment->update(['reference' => $sandboxId, 'data' => $data]);

        return $sandboxId;
    }

    /**
     * Pays in the sandbox with the published test card and waits for the capture.
     *
     * @return array{id: string, source: array<string, mixed>}
     */
    public function payInSandbox(float $amount, string $currency, ?string $channel, string $reference): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sandbox payments are for local QA only');
        }

        $api = CheckoutSdk::builder()->staticKeys()
            ->publicKey(config('app.sandbox.checkout_com.public_key'))
            ->secretKey(config('app.sandbox.checkout_com.secret_key'))
            ->environment(Environment::sandbox())
            ->build();

        $card               = new RequestCardSource();
        $card->number       = '4242424242424242';
        $card->expiry_month = 12;
        $card->expiry_year  = (int) now()->addYears(3)->format('Y');
        $card->cvv          = '100';

        $request                        = new PaymentRequest();
        $request->source                = $card;
        $request->amount                = (int) round($amount * 100);
        $request->currency              = $currency;
        $request->capture               = true;
        $request->reference             = $reference;
        $request->processing_channel_id = $channel ?: (self::CHANNEL_BY_CURRENCY[$currency] ?? config('app.sandbox.checkout_com.payment_channel'));

        try {
            $sandboxId = (string) $api->getPaymentsClient()->requestPayment($request)['id'];
        } catch (CheckoutApiException $e) {
            throw new RuntimeException('The sandbox refused the payment: '.implode(', ', Arr::get($e->error_details ?? [], 'error_codes', [$e->getMessage()])));
        }

        for ($try = 0; $try < 10; $try++) {
            $details = $api->getPaymentsClient()->getPaymentDetails($sandboxId);
            if (Arr::get($details, 'status') === 'Captured') {
                return ['id' => $sandboxId, 'source' => Arr::only((array) Arr::get($details, 'source', []), ['type', 'scheme', 'card_wallet_type'])];
            }
            sleep(1);
        }

        throw new RuntimeException("The sandbox payment $sandboxId was not captured in time, nothing was changed in aiku");
    }

    private function checkoutAccountShop(Shop $shop): PaymentAccountShop
    {
        return $shop->paymentAccountShops()->where('type', PaymentAccountTypeEnum::CHECKOUT)->where('state', PaymentAccountShopStateEnum::ACTIVE)->first()
            ?? throw new RuntimeException("Shop $shop->slug has no active Checkout.com payment account");
    }

    /**
     * A card top up as the website records it: a Checkout.com payment, its top up and the TOP_UP credit.
     */
    public function topUp(Customer $customer, float $amount, ?string $channel = null): Payment
    {
        $paymentAccountShop = $this->checkoutAccountShop($customer->shop);
        $sandbox            = $this->payInSandbox($amount, $customer->shop->currency->code, $channel, 'aiku-local-qa-topup-'.$customer->id);

        return DB::transaction(function () use ($customer, $amount, $paymentAccountShop, $sandbox) {
            $payment = StorePayment::run($customer, $paymentAccountShop->paymentAccount, [
                'reference'               => $sandbox['id'],
                'amount'                  => $amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::PAYMENT,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'class'                   => PaymentClassEnum::TOPUP,
                'source'                  => $sandbox['source'],
            ]);
            $topUp = StoreTopUp::run($payment, ['amount' => $amount, 'status' => TopUpStatusEnum::SUCCESS]);
            StoreCreditTransaction::run($customer, [
                'amount'     => $amount,
                'payment_id' => $payment->id,
                'top_up_id'  => $topUp->id,
                'type'       => CreditTransactionTypeEnum::TOP_UP,
            ]);

            return $payment;
        });
    }

    /**
     * Pays an order by card as staff would record a card payment: what is still unpaid, or the amount given.
     */
    public function payOrder(Order $order, ?float $amount = null, ?string $channel = null): Payment
    {
        $amount = round($amount ?? ((float) $order->total_amount - (float) $order->payment_amount), 2);
        if ($amount <= 0) {
            throw new RuntimeException("Order $order->reference has nothing left to pay, give --amount");
        }

        $paymentAccountShop = $this->checkoutAccountShop($order->shop);
        $sandbox            = $this->payInSandbox($amount, $order->currency->code, $channel, 'aiku-local-qa-'.$order->reference);

        return PayOrder::make()->handle($order, $paymentAccountShop->paymentAccount, [
            'reference'               => $sandbox['id'],
            'amount'                  => $amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'state'                   => PaymentStateEnum::COMPLETED,
            'type'                    => PaymentTypeEnum::PAYMENT,
            'payment_account_shop_id' => $paymentAccountShop->id,
            'source'                  => $sandbox['source'],
        ]);
    }

    public function restore(Payment $payment): ?string
    {
        $original = Arr::get($payment->data, 'sandbox_original_reference');
        if (!$original) {
            return null;
        }
        $data = $payment->data;
        Arr::forget($data, 'sandbox_original_reference');
        $payment->update(['reference' => $original, 'data' => $data]);

        return $original;
    }

    /**
     * @return array<string, int|null>
     */
    public function suggest(): array
    {
        $checkoutPayments = fn (): Builder => Payment::where('type', PaymentTypeEnum::PAYMENT)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->whereNot('state', PaymentStateEnum::CANCELLED)
            ->where('reference', 'like', 'pay\_%')
            ->whereColumn('total_refund', '<', 'amount')
            ->whereHas('paymentAccount', fn ($query) => $query->where('type', PaymentAccountTypeEnum::CHECKOUT))
            ->orderByDesc('id');

        $withOpenRefundInvoice = $checkoutPayments()->whereHas('invoices', fn ($invoices) => $invoices->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->whereExists(fn ($refunds) => $refunds->selectRaw(1)->from('invoices as refunds')
                ->whereColumn('refunds.original_invoice_id', 'invoices.id')
                ->where('refunds.type', InvoiceTypeEnum::REFUND->value)
                ->where('refunds.in_process', false)
                ->whereNull('refunds.deleted_at')
                ->whereRaw('abs(refunds.total_amount) > abs(refunds.payment_amount)')));

        return [
            'invoiced, open refund invoice'    => $withOpenRefundInvoice->value('id'),
            'invoiced, no refund invoice'      => $checkoutPayments()->whereHas('invoices', fn ($invoices) => $invoices->where('invoices.type', InvoiceTypeEnum::INVOICE)->whereNotExists(fn ($refunds) => $refunds->selectRaw(1)->from('invoices as refunds')->whereColumn('refunds.original_invoice_id', 'invoices.id')))->value('id'),
            'cancelled order (credit)'         => $checkoutPayments()->whereDoesntHave('invoices')->whereHas('orders', fn ($orders) => $orders->where('orders.state', OrderStateEnum::CANCELLED))->get()->first(fn (Payment $payment) => RefundPaymentByApi::creditRefundableLeft($payment) > 0)?->id,
            'top up (credit)'                  => $checkoutPayments()->whereIn('id', \App\Models\Accounting\CreditTransaction::where('type', CreditTransactionTypeEnum::TOP_UP)->whereNotNull('payment_id')->select('payment_id'))->limit(50)->get()->first(fn (Payment $payment) => RefundPaymentByApi::creditRefundableLeft($payment) > 0)?->id,
            'live order (refused)'             => $checkoutPayments()->whereDoesntHave('invoices')->whereHas('orders', fn ($orders) => $orders->whereNotIn('orders.state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING]))->value('id'),
        ];
    }

    public function asCommand(Command $command): int
    {
        if ($command->option('suggest')) {
            foreach ($this->suggest() as $case => $paymentId) {
                $payment = $paymentId ? Payment::find($paymentId) : null;
                $command->line(str_pad($case, 34).($payment ? "payment $payment->id · {$payment->organisation->slug} · {$payment->currency->code} {$payment->amount} · /org/{$payment->organisation->slug}/accounting/payments/$payment->id" : 'none found'));
            }

            return 0;
        }

        try {
            if ($customerId = $command->option('top-up')) {
                $payment = $this->topUp(Customer::findOrFail($customerId), (float) ($command->option('amount') ?: 20), $command->option('channel'));
                $command->info("Top up of {$payment->currency->code} $payment->amount for customer $customerId is in their credit, paid by sandbox payment $payment->reference (payment $payment->id: /org/{$payment->organisation->slug}/accounting/payments/$payment->id)");

                return 0;
            }
            if ($orderId = $command->option('pay-order')) {
                $order   = Order::findOrFail($orderId);
                $payment = $this->payOrder($order, $command->option('amount') ? (float) $command->option('amount') : null, $command->option('channel'));
                $command->info("Order $order->reference paid {$payment->currency->code} $payment->amount by sandbox payment $payment->reference (payment $payment->id: /org/{$payment->organisation->slug}/accounting/payments/$payment->id)");

                return 0;
            }
        } catch (RuntimeException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $payment = Payment::find($command->argument('payment'));
        if (!$payment) {
            $command->error('Give a local payment id, or --suggest');

            return 1;
        }

        if ($command->option('restore')) {
            $original = $this->restore($payment);
            $original ? $command->info("Payment $payment->id is back on $original") : $command->warn("Payment $payment->id was not linked to the sandbox");

            return 0;
        }

        try {
            $sandboxId = $this->handle($payment, $command->option('channel'));
        } catch (RuntimeException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $command->info("Payment $payment->id ({$payment->currency->code} $payment->amount) now points at sandbox payment $sandboxId. Put it back with: php artisan checkout:sandbox-payment $payment->id --restore");

        return 0;
    }
}

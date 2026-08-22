<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Aug 2026 14:20:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\WithCheckoutCom;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\FetchAuroraPayment;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Repairs payments.method for checkout.com payments recorded before alternative payment methods
 * (klarna, paypal...) were named. Native payments have no stored source, so the method is read
 * back from checkout.com by payment id; Aurora-fetched payments (--aurora) read it from Aurora's
 * Payment Metadata instead, no API call. Only the method column is written, nothing else on the
 * payment is touched. Dry run unless --commit is given.
 */
class BackfillCheckoutComPaymentMethods
{
    use AsAction;
    use WithCheckoutCom;

    public string $commandSignature = 'payments:backfill_checkout_com_methods {--commit} {--aurora : repair Aurora-fetched payments from Payment Metadata instead of the checkout.com API} {--shop=} {--limit=0} {--sleep=250 : milliseconds between checkout.com calls, keeps the backfill well under the API rate limit}';

    public function asCommand(Command $command): int
    {
        $commit = (bool) $command->option('commit');
        $limit  = (int) $command->option('limit');
        $aurora = (bool) $command->option('aurora');
        $this->sleepMicroseconds = max(0, (int) $command->option('sleep')) * 1000;

        $query = Payment::query()
            ->join('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->where('payment_accounts.type', PaymentAccountTypeEnum::CHECKOUT)
            ->select('payments.*')
            ->orderBy('payments.id');

        $unresolved = fn ($q) => $q
            ->whereRaw('payments.method = payment_accounts.type')
            ->orWhere(fn ($q) => $q->whereIn('payments.method', ['card', 'applepay', 'googlepay'])->whereNull('payments.sub_method'));

        if ($aurora) {
            $query->whereNotNull('payments.source_id')
                ->where(fn ($q) => $q->whereNull('payments.method')->orWhere($unresolved));
        } else {
            $query->where($unresolved)
                ->whereNull('payments.source_id')
                ->where('payments.reference', 'like', 'pay\_%');
        }

        if ($command->option('shop')) {
            $query->whereIn('payments.shop_id', Shop::where('slug', $command->option('shop'))->select('id'));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $counts    = ['updated' => 0, 'unchanged' => 0, 'error' => 0];
        $byMethod  = [];
        $accountShops = [];

        foreach ($query->cursor() as $payment) {
            $source = $aurora ? $this->sourceFromAurora($payment) : $this->sourceFromCheckoutCom($payment, $accountShops, $command);
            if (!$source) {
                $counts['error']++;
                if (!$aurora) {
                    $command->warn("Payment {$payment->id} ({$payment->reference}): no source from checkout.com");
                }
                continue;
            }

            $derived = StorePayment::methodFromSource($source, $payment->paymentAccount);

            if ($derived['method'] === $payment->method && $derived['sub_method'] === $payment->sub_method) {
                $counts['unchanged']++;
                continue;
            }

            $key            = $derived['method'].($derived['sub_method'] ? ' / '.$derived['sub_method'] : '');
            $byMethod[$key] = ($byMethod[$key] ?? 0) + 1;
            $counts['updated']++;

            if ($commit) {
                DB::table('payments')->where('id', $payment->id)->update($derived);
            } else {
                $command->line("Payment {$payment->id} ({$payment->reference}): {$payment->method}/{$payment->sub_method} -> {$key}");
            }
        }

        ksort($byMethod);
        $command->table(['method', 'payments'], collect($byMethod)->map(fn ($n, $m) => [$m, $n])->values()->all());
        $command->info(($commit ? 'Updated' : 'Would update')." {$counts['updated']}, unchanged {$counts['unchanged']}, errors {$counts['error']}");

        return 0;
    }

    private function sourceFromCheckoutCom(Payment $payment, array &$accountShops, Command $command): ?array
    {
        $paymentAccountShop = $this->paymentAccountShopFor($payment, $accountShops);
        if (!$paymentAccountShop) {
            $command->warn("Payment {$payment->id}: no active checkout.com account shop");

            return null;
        }

        usleep($this->sleepMicroseconds);
        $checkoutComPayment = $this->getCheckOutPayment($paymentAccountShop, $payment->reference);
        if (Arr::get($checkoutComPayment, 'error')) {
            $command->warn("Payment {$payment->id} ({$payment->reference}): ".json_encode(Arr::only($checkoutComPayment, ['http_status_code', 'message'])));

            return null;
        }

        return Arr::get($checkoutComPayment, 'source');
    }

    private function sourceFromAurora(Payment $payment): ?array
    {
        [$organisationId, $auroraPaymentKey] = explode(':', $payment->source_id);

        if ($this->auroraOrganisationId !== (int) $organisationId) {
            $this->auroraOrganisationId = (int) $organisationId;
            (new AuroraOrganisationService())->initialisation(Organisation::find($organisationId));
        }

        $metadata = DB::connection('aurora')->table('Payment Dimension')
            ->where('Payment Key', $auroraPaymentKey)
            ->value('Payment Metadata');

        return FetchAuroraPayment::sourceFromMetadata($metadata);
    }

    private ?int $auroraOrganisationId = null;

    private int $sleepMicroseconds = 250000;

    private function paymentAccountShopFor(Payment $payment, array &$cache): ?PaymentAccountShop
    {
        if ($payment->payment_account_shop_id) {
            return $cache[$payment->payment_account_shop_id] ??= PaymentAccountShop::find($payment->payment_account_shop_id);
        }

        $key = 'shop-'.$payment->shop_id;

        return $cache[$key] ??= PaymentAccountShop::where('shop_id', $payment->shop_id)
            ->where('payment_account_id', $payment->payment_account_id)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->first();
    }
}

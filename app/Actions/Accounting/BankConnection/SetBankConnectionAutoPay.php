<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection;

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentAccount;
use App\Models\Helpers\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Switches automatic payment of matched bank lines on or off for a connection. Only lines booked after it is switched
 * on are ever paid; history keeps its labels.
 */
class SetBankConnectionAutoPay
{
    use AsAction;

    public string $commandSignature = 'bank-connections:auto-pay {bankConnection : id}
        {--account=* : CURRENCY=payment-account-code, e.g. --account=PLN=sk-bank-pln2}
        {--fee-ceiling-eur= : largest bank fee absorbed on one transfer, in EUR}
        {--off : stop paying automatically}';

    /**
     * @param array<string, string> $paymentAccountCodes currency code => payment account code
     */
    public function handle(BankConnection $bankConnection, array $paymentAccountCodes, ?float $feeCeilingEur = null, bool $off = false): BankConnection
    {
        $settings = $bankConnection->settings;

        if ($off) {
            Arr::forget($settings, 'auto_pay_from');
            $bankConnection->update(['settings' => $settings]);

            return $bankConnection;
        }

        foreach ($paymentAccountCodes as $currencyCode => $paymentAccountCode) {
            if (!Currency::where('code', strtoupper($currencyCode))->exists()) {
                throw ValidationException::withMessages(['account' => "Unknown currency $currencyCode"]);
            }
            $paymentAccount = PaymentAccount::where('organisation_id', $bankConnection->organisation_id)
                ->where('code', $paymentAccountCode)
                ->where('type', PaymentAccountTypeEnum::BANK)
                ->first();
            if (!$paymentAccount) {
                throw ValidationException::withMessages(['account' => "No bank payment account $paymentAccountCode in this organisation"]);
            }
            $settings['payment_accounts'][strtoupper($currencyCode)] = $paymentAccount->id;
        }

        if (empty($settings['payment_accounts'])) {
            throw ValidationException::withMessages(['account' => 'Map at least one currency to a bank payment account']);
        }

        $settings['bank_fee_payment_account_id'] = $this->bankFeePaymentAccount($bankConnection)->id;
        $settings['fee_ceiling_eur']             = $feeCeilingEur ?? $settings['fee_ceiling_eur'] ?? MatchBankTransactions::DEFAULT_FEE_CEILING_EUR;
        $settings['auto_pay_from'] ??= now()->toIso8601String();

        $bankConnection->update(['settings' => $settings]);

        return $bankConnection;
    }

    private function bankFeePaymentAccount(BankConnection $bankConnection): PaymentAccount
    {
        $existing = PaymentAccount::where('organisation_id', $bankConnection->organisation_id)
            ->where('type', PaymentAccountTypeEnum::BANK_FEE)
            ->first();
        if ($existing) {
            return $existing;
        }

        $orgPaymentServiceProvider = OrgPaymentServiceProvider::where('organisation_id', $bankConnection->organisation_id)
            ->whereHas('paymentServiceProvider', fn ($query) => $query->where('code', 'bank'))
            ->first();
        if (!$orgPaymentServiceProvider) {
            throw ValidationException::withMessages(['account' => 'This organisation has no bank payment service provider to hold the bank fee account']);
        }

        return StorePaymentAccount::make()->action($orgPaymentServiceProvider, [
            'type' => PaymentAccountTypeEnum::BANK_FEE->value,
            'code' => 'bank-fees',
            'name' => __('Bank fees absorbed'),
        ]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $paymentAccountCodes = [];
        foreach ($command->option('account') as $mapping) {
            [$currencyCode, $paymentAccountCode] = array_pad(explode('=', $mapping, 2), 2, null);
            if (!$currencyCode || !$paymentAccountCode) {
                $command->error("Use CURRENCY=payment-account-code, got $mapping");

                return 1;
            }
            $paymentAccountCodes[$currencyCode] = $paymentAccountCode;
        }

        $bankConnection = $this->handle(
            BankConnection::findOrFail($command->argument('bankConnection')),
            $paymentAccountCodes,
            $command->option('fee-ceiling-eur') !== null ? (float)$command->option('fee-ceiling-eur') : null,
            (bool)$command->option('off')
        );

        $settings = $bankConnection->settings;
        if (empty($settings['auto_pay_from'])) {
            $command->info('Auto-pay is off');
        } else {
            $command->info("Auto-pay on for lines booked from {$settings['auto_pay_from']}, bank fees up to {$settings['fee_ceiling_eur']} EUR absorbed");
        }

        return 0;
    }
}

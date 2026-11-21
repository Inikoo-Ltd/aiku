<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection;

use App\Actions\Accounting\BankPayee\ClassifyBankOutgoing;
use App\Actions\Accounting\BankConnection\Fio\SyncFioBankConnection;
use App\Actions\Accounting\BankConnection\Tatra\SyncTatraBankConnection;
use App\Actions\Accounting\BankConnection\Wise\SyncWiseBankConnection;
use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\Accounting\BankTransaction\PayMatchedBankTransactions;
use App\Actions\SupplyChain\DepositRequest\MatchAgentMoneyTransfers;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankConnection\BankConnectionStateEnum;
use App\Actions\Helpers\Currency\SetCurrencyHistoricFields;
use App\Models\Accounting\BankBalance;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Currency;
use Illuminate\Support\Carbon;
use App\Models\Accounting\BankConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Sentry;
use Throwable;

class SyncBankConnection
{
    use AsAction;

    public const int RATES_SINCE_YEARS = 2;

    public string $commandSignature = 'bank-connections:sync {bankConnection? : id; when omitted every connection already backfilled} {--full : read the whole history}';

    /**
     * @return array{synced: int, skipped: int, skipped_examples: list<string>}|null
     */
    public function handle(BankConnection $bankConnection, bool $full = false): ?array
    {
        if ($bankConnection->state === BankConnectionStateEnum::DISABLED) {
            return null;
        }

        $lock = Cache::lock('bank-connection-sync:'.$bankConnection->id, 6 * 3600);
        if (!$lock->get()) {
            return null;
        }

        try {
            return $this->sync($bankConnection, $full);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{synced: int, skipped: int, skipped_examples: list<string>}|null
     */
    private function sync(BankConnection $bankConnection, bool $full): ?array
    {
        $startedAt = now();

        try {
            $result = match ($bankConnection->provider) {
                BankConnectionProviderEnum::WISE => SyncWiseBankConnection::run($bankConnection, $full),
                BankConnectionProviderEnum::FIO => SyncFioBankConnection::run($bankConnection, $full),
                BankConnectionProviderEnum::TATRA => SyncTatraBankConnection::run($bankConnection, $full),
                default => throw new RuntimeException("No bank sync for provider {$bankConnection->provider->value}"),
            };
        } catch (Throwable $exception) {
            $bankConnection->update([
                'state'         => BankConnectionStateEnum::ERROR,
                'last_error'    => mb_substr($exception->getMessage(), 0, 2000),
                'last_error_at' => now(),
            ]);
            Sentry::captureException($exception);

            return null;
        }

        if ($result['skipped']) {
            Sentry::captureMessage("Bank connection $bankConnection->id skipped {$result['skipped']} lines it could not read: ".implode('; ', $result['skipped_examples']));
        }

        $bankConnection->update([
            'state'          => BankConnectionStateEnum::ACTIVE,
            'last_synced_at' => $startedAt,
            'last_error'     => null,
            'sync_data'      => array_merge($bankConnection->sync_data, ['last_result' => $result]),
        ]);

        FetchBankConnectionBalances::run($bankConnection);
        self::keepExchangeRates($bankConnection);

        try {
            MatchBankTransactions::run($bankConnection);
            PayMatchedBankTransactions::run($bankConnection->refresh());
        } catch (Throwable $exception) {
            Sentry::captureException($exception);
        }

        try {
            ClassifyBankOutgoing::run($bankConnection->group);
        } catch (Throwable $exception) {
            Sentry::captureException($exception);
        }

        try {
            MatchAgentMoneyTransfers::run($bankConnection->organisation);
        } catch (Throwable $exception) {
            Sentry::captureException($exception);
        }

        return $result;
    }

    /**
     * Every currency the bank holds or moved money in keeps a daily exchange rate, so its lines and balances can be
     * shown in the organisation's currency: from its first line, but no further back than RATES_SINCE_YEARS.
     */
    public static function keepExchangeRates(BankConnection $bankConnection): void
    {
        $currencyIds = BankTransaction::where('bank_connection_id', $bankConnection->id)->distinct()->pluck('currency_id')
            ->merge(BankBalance::where('bank_connection_id', $bankConnection->id)->distinct()->pluck('currency_id'))
            ->unique();

        Currency::whereIn('id', $currencyIds)->where('store_historic_data', false)->get()->each(function (Currency $currency) use ($bankConnection) {
            $firstLine = BankTransaction::where('bank_connection_id', $bankConnection->id)->where('currency_id', $currency->id)->min('booked_at');
            SetCurrencyHistoricFields::run($currency, Carbon::parse($firstLine ?? now())->max(now()->subYears(self::RATES_SINCE_YEARS)->startOfDay()));
        });
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $bankConnections = $command->argument('bankConnection')
            ? BankConnection::whereKey($command->argument('bankConnection'))->get()
            : BankConnection::where('state', '!=', BankConnectionStateEnum::DISABLED)->whereNotNull('last_synced_at')->get();

        $failed = false;
        foreach ($bankConnections as $bankConnection) {
            $result = $this->handle($bankConnection, (bool)$command->option('full'));
            if ($result === null) {
                $failed = true;
                $command->error("$bankConnection->id $bankConnection->name: not synced (disabled, already running, or failed: ".$bankConnection->refresh()->last_error.')');
            } else {
                $command->info("$bankConnection->id $bankConnection->name: {$result['synced']} synced, {$result['skipped']} skipped");
            }
        }

        return $failed ? 1 : 0;
    }
}

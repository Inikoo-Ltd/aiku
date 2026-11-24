<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection;

use App\Actions\Accounting\BankConnection\Wise\WithWiseApi;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankConnection\BankConnectionStateEnum;
use App\Models\Accounting\BankBalance;
use App\Models\Accounting\BankConnection;
use App\Models\Helpers\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Sentry;
use Throwable;

/**
 * Reads what a bank connection holds now and keeps it as the day's snapshot, one row per currency. A reading that
 * fails never throws: it is recorded on the connection (sync_data balances_error) and the last snapshot stays,
 * with its own time, so it is never mistaken for a current one.
 */
class FetchBankConnectionBalances
{
    use AsAction;
    use WithWiseApi;

    public string $commandSignature = 'bank-connections:balances {bankConnection? : id; when omitted every connection that is not disabled}';

    /**
     * @return int|null how many currencies were stored; null when the balances could not be read
     */
    public function handle(BankConnection $bankConnection): ?int
    {
        try {
            $balances = match ($bankConnection->provider) {
                BankConnectionProviderEnum::WISE => $this->wiseBalances($bankConnection),
                BankConnectionProviderEnum::FIO => $this->syncedBalance($bankConnection, 'fio_balance'),
                BankConnectionProviderEnum::TATRA => $this->syncedBalance($bankConnection, 'tatra_balance'),
                BankConnectionProviderEnum::PAYPAL => $bankConnection->sync_data['paypal_balances'] ?? throw new RuntimeException('No paypal_balances read yet'),
                default => throw new RuntimeException("No bank balances for provider {$bankConnection->provider->value}"),
            };
            $stored = $this->store($bankConnection, $balances);
        } catch (Throwable $exception) {
            $bankConnection->update([
                'sync_data' => array_merge($bankConnection->sync_data, [
                    'balances_error'    => mb_substr($exception->getMessage(), 0, 2000),
                    'balances_error_at' => now()->toIso8601String(),
                ]),
            ]);
            Sentry::captureException($exception);

            return null;
        }

        $bankConnection->update(['sync_data' => Arr::except($bankConnection->sync_data, ['balances_error', 'balances_error_at'])]);

        return $stored;
    }

    /**
     * Wise keeps several balances in one currency (an emptied one beside the one in use, and savings jars): they are added together.
     *
     * @return array<string, array{amount: float, reserved_amount: float}> by currency code
     */
    private function wiseBalances(BankConnection $bankConnection): array
    {
        $balances = [];
        foreach ($this->wiseGet($bankConnection->credentials, "/v4/profiles/$bankConnection->external_id/balances", ['types' => 'STANDARD,SAVINGS']) ?? [] as $balance) {
            $code = $balance['currency'] ?? null;
            if (!is_string($code) || !is_numeric($balance['amount']['value'] ?? null)) {
                throw new RuntimeException('Unreadable Wise balance: '.mb_substr(json_encode($balance), 0, 300));
            }
            $balances[$code] ??= ['amount' => 0.0, 'reserved_amount' => 0.0];
            $balances[$code]['amount']          += (float) $balance['amount']['value'];
            $balances[$code]['reserved_amount'] += (float) ($balance['reservedAmount']['value'] ?? 0);
        }
        if (!$balances) {
            throw new RuntimeException('Wise returned no balances');
        }

        return $balances;
    }

    /**
     * Fio answers one call per token every 30 seconds and Tatra lists the balance with the account, so for both the
     * balance is the one the sync just read into sync_data.
     *
     * @return array<string, array{amount: float, reserved_amount: float}>
     */
    private function syncedBalance(BankConnection $bankConnection, string $key): array
    {
        $balance = $bankConnection->sync_data[$key] ?? [];
        if (!is_string($balance['currency'] ?? null) || !is_numeric($balance['amount'] ?? null)) {
            throw new RuntimeException("No $key read yet");
        }

        return [$balance['currency'] => ['amount' => (float)$balance['amount'], 'reserved_amount' => 0.0]];
    }

    /**
     * @param array<string, array{amount: float, reserved_amount: float}> $balances
     */
    private function store(BankConnection $bankConnection, array $balances): int
    {
        $currencyIds = Currency::whereIn('code', array_keys($balances))->pluck('id', 'code');
        $unknown     = array_diff(array_keys($balances), $currencyIds->keys()->all());
        if ($unknown) {
            throw new RuntimeException('Unknown currency: '.implode(', ', $unknown));
        }

        $now  = now();
        $rows = collect($balances)->map(fn (array $balance, string $code) => [
            'group_id'           => $bankConnection->group_id,
            'organisation_id'    => $bankConnection->organisation_id,
            'bank_connection_id' => $bankConnection->id,
            'currency_id'        => $currencyIds[$code],
            'date'               => $now->toDateString(),
            'amount'             => round($balance['amount'], 2),
            'reserved_amount'    => round($balance['reserved_amount'], 2),
            'taken_at'           => $now,
        ])->values()->all();

        DB::transaction(function () use ($bankConnection, $rows, $now) {
            BankBalance::where('bank_connection_id', $bankConnection->id)
                ->where('date', $now->toDateString())
                ->whereNotIn('currency_id', array_column($rows, 'currency_id'))
                ->delete();
            BankBalance::upsert($rows, ['bank_connection_id', 'currency_id', 'date'], ['amount', 'reserved_amount', 'taken_at']);
        });

        return count($rows);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $bankConnections = $command->argument('bankConnection')
            ? BankConnection::whereKey($command->argument('bankConnection'))->get()
            : BankConnection::where('state', '!=', BankConnectionStateEnum::DISABLED)->get();

        $failed = false;
        foreach ($bankConnections as $bankConnection) {
            $stored = $this->handle($bankConnection);
            if ($stored === null) {
                $failed = true;
                $command->error("$bankConnection->id $bankConnection->name: balances not read: ".($bankConnection->sync_data['balances_error'] ?? ''));
            } else {
                $command->info("$bankConnection->id $bankConnection->name: $stored currencies");
            }
        }

        return $failed ? 1 : 0;
    }
}

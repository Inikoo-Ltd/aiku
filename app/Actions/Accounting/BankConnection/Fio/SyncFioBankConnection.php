<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection\Fio;

use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Currency;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mirrors one Fio account (one token per account) into bank_transactions from the dated statement of /periods/, so a
 * run can be repeated safely. Fio lists booked movements only and never changes them; its fees are their own lines.
 * Fio answers one call per token every 30 seconds (409 otherwise), and history older than 90 days only for 10
 * minutes after it is unlocked in Internetbanking, so a full sync reads the last 89 days.
 */
class SyncFioBankConnection
{
    use AsAction;

    public const int FULL_HISTORY_DAYS = 89;

    /** @var array<string, int|null> */
    private array $currencyIds = [];

    /**
     * @return array{synced: int, skipped: int, skipped_examples: list<string>}
     */
    public function handle(BankConnection $bankConnection, bool $full = false): array
    {
        $today = now('Europe/Prague')->toDateString();
        $from  = ($full || !$bankConnection->last_synced_at)
            ? now('Europe/Prague')->subDays(self::FULL_HISTORY_DAYS)->toDateString()
            : $bankConnection->last_synced_at->copy()->setTimezone('Europe/Prague')->subDays(3)->toDateString();

        $statement = $this->fioGet($bankConnection->credentials, "/periods/{token}/$from/$today/transactions.json")['accountStatement'] ?? [];
        $info      = $statement['info'] ?? [];

        $bankConnection->update([
            'external_id' => $bankConnection->external_id ?? (isset($info['accountId']) ? (string)$info['accountId'] : null),
            'sync_data'   => array_merge($bankConnection->sync_data, [
                'fio_balance' => [
                    'currency' => $info['currency'] ?? null,
                    'amount'   => $info['closingBalance'] ?? null,
                    'taken_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $movements = $statement['transactionList']['transaction'] ?? [];
        $existing  = BankTransaction::where('bank_connection_id', $bankConnection->id)
            ->whereIn('provider_transaction_id', array_map(fn ($movement) => (string)$this->column($movement, 22), $movements))
            ->pluck('provider_transaction_id')
            ->all();

        $synced          = 0;
        $skipped         = 0;
        $skippedExamples = [];
        foreach ($movements as $movement) {
            $result = $this->insertMovement($bankConnection, $movement, $existing);
            if (is_string($result)) {
                $skipped++;
                if (count($skippedExamples) < 20) {
                    $skippedExamples[] = $this->column($movement, 22).': '.$result;
                }
            } elseif ($result) {
                $synced++;
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'skipped_examples' => $skippedExamples];
    }

    /**
     * Columns used: 0 date, 1 signed amount, 2 counter account, 5 variable symbol, 7 user identification,
     * 8 type, 10 counter account name, 14 currency, 16 message for the recipient, 22 movement id, 25 comment, 27 payer reference.
     * A movement with a counter account is a TRANSFER, the type order matching reads; card payments, fees and interest are not.
     *
     * @param list<string> $existing
     *
     * @return bool|string true written, false already stored, a string says why the movement was not understood
     */
    private function insertMovement(BankConnection $bankConnection, array $movement, array $existing): bool|string
    {
        $id = $this->column($movement, 22);
        if ($id === null) {
            return 'no movement id';
        }
        $id = (string)$id;
        if (in_array($id, $existing)) {
            return false;
        }

        $amount = $this->column($movement, 1);
        $date   = (string)$this->column($movement, 0);
        if (!is_numeric($amount) || (float)$amount == 0.0 || !preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return 'amount or date not understood';
        }

        $currencyCode = (string)$this->column($movement, 14);
        $currencyId   = $this->currencyIds[$currencyCode] ??= Currency::where('code', $currencyCode)->value('id');
        if (!$currencyId) {
            return 'unknown currency: '.$currencyCode;
        }

        $counterAccount = $this->text($this->column($movement, 2));
        $bookedAt       = Carbon::parse(substr($date, 0, 10), 'Europe/Prague');

        BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => $id,
            'provider_type'           => $counterAccount ? 'TRANSFER' : 'OTHER',
            'direction'               => (float)$amount > 0 ? BankTransactionDirectionEnum::IN : BankTransactionDirectionEnum::OUT,
            'amount'                  => round((float)$amount, 2),
            'currency_id'             => $currencyId,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'counterparty_name'       => $this->text($this->column($movement, 10)) ?? $this->text($this->column($movement, 7)),
            'reference'               => $this->text($this->column($movement, 16)) ?? $this->text($this->column($movement, 27)) ?? $this->text($this->column($movement, 5)),
            'description'             => $this->text($this->column($movement, 8)),
            'booked_at'               => $bookedAt,
            'provider_updated_at'     => $bookedAt,
            'data'                    => ['movement' => $movement],
        ]);

        return true;
    }

    private function column(array $movement, int $column): mixed
    {
        return $movement['column'.$column]['value'] ?? null;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    /**
     * Fio puts the token in the URL path, so a connection error message is stripped of it before it reaches last_error or Sentry.
     */
    public function fioGet(#[\SensitiveParameter] string $token, string $path): ?array
    {
        try {
            return $this->fioRequest($token, $path);
        } catch (ConnectionException $exception) {
            throw new ConnectionException(str_replace($token, '***', $exception->getMessage()));
        }
    }

    private function fioRequest(#[\SensitiveParameter] string $token, string $path): ?array
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(
                3,
                fn (int $attempt, \Throwable $exception) => $exception instanceof RequestException && $exception->response->status() === 409 ? 31000 : $attempt * 2000,
                fn (\Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->status() === 409 || $exception->response->serverError())),
                throw: false
            )
            ->get('https://fioapi.fio.cz/v1/rest'.str_replace('{token}', $token, $path))
            ->throw()
            ->json();
    }
}

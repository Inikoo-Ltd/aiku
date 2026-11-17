<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection\Tatra;

use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Currency;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Mirrors one Tatra banka account into bank_transactions through Premium API - Accounts with Business banking consent
 * (usage FAC BBTB): the client credentials grant gives a 24-hour token, the consent itself is given and renewed by the
 * company in Business banking. Credentials are stored as "client_id:client_secret"; external_id is the Tatra accountId,
 * taken from the account list when the consent reaches exactly one account. Booked movements only, 15 months of history.
 * Until real lines confirm whether Tatra signs amounts, a line whose direction is not certain (reversal with a positive
 * amount, neither or both sides our IBAN) is skipped and reported rather than stored, since stored lines feed auto-pay.
 */
class SyncTatraBankConnection
{
    use AsAction;

    public const string BASE_URL = 'https://api.tatrabanka.sk/premium/production';

    public const int FULL_HISTORY_MONTHS = 15;

    public const int PAGE_SIZE = 1000;

    /** @var array<string, int|null> */
    private array $currencyIds = [];

    /**
     * @return array{synced: int, skipped: int, skipped_examples: list<string>}
     */
    public function handle(BankConnection $bankConnection, bool $full = false): array
    {
        $token   = $this->accessToken($bankConnection->credentials);
        $account = $this->account($bankConnection, $token);
        $iban    = $account['accountReference']['iban'] ?? null;

        $bankConnection->update([
            'external_id' => $account['accountId'],
            'sync_data'   => array_merge($bankConnection->sync_data, [
                'tatra_iban'               => $iban,
                'tatra_consent_expires_at' => $account['consentExpirationDate'] ?? null,
                'tatra_balance'            => $this->balance($account),
            ]),
        ]);

        $today = now('Europe/Bratislava');
        $from  = ($full || !$bankConnection->last_synced_at)
            ? $today->copy()->subMonthsNoOverflow(self::FULL_HISTORY_MONTHS)->addDay()
            : $bankConnection->last_synced_at->copy()->setTimezone('Europe/Bratislava')->subDays(3);

        $transactions = $this->transactions($token, $account['accountId'], $from->toDateString(), $today->toDateString());
        $existing     = BankTransaction::where('bank_connection_id', $bankConnection->id)
            ->whereIn('provider_transaction_id', array_filter(array_map(fn ($transaction) => $transaction['transactionId'] ?? null, $transactions)))
            ->pluck('provider_transaction_id')
            ->all();

        $synced          = 0;
        $skipped         = 0;
        $skippedExamples = [];
        foreach ($transactions as $transaction) {
            $result = $this->insertTransaction($bankConnection, $transaction, $iban, $existing);
            if (is_string($result)) {
                $skipped++;
                if (count($skippedExamples) < 20) {
                    $skippedExamples[] = ($transaction['transactionId'] ?? '?').': '.$result;
                }
            } elseif ($result) {
                $synced++;
                $existing[] = (string)$transaction['transactionId'];
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'skipped_examples' => $skippedExamples];
    }

    /**
     * The direction comes from the sign when Tatra signs the amount, otherwise from which side holds our IBAN.
     *
     * @param list<string> $existing
     *
     * @return bool|string true written, false already stored, a string says why the transaction was not understood
     */
    private function insertTransaction(BankConnection $bankConnection, array $transaction, ?string $iban, array $existing): bool|string
    {
        $id = $transaction['transactionId'] ?? null;
        if (!is_string($id) || $id === '') {
            return 'no transaction id';
        }
        if (in_array($id, $existing)) {
            return false;
        }

        $amount = $transaction['transactionAmount']['amount'] ?? null;
        $date   = (string)($transaction['bookingDate'] ?? '');
        if (!is_numeric($amount) || (float)$amount == 0.0 || !preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return 'amount or date not understood';
        }

        $state = $transaction['transactionState'] ?? 'BOOKED';
        if (strtoupper((string)$state) !== 'BOOKED') {
            return 'not booked: '.$state;
        }
        if (!empty($transaction['isReversal']) && (float)$amount > 0) {
            return 'reversal with an unsigned amount, direction not certain';
        }

        $direction = $this->direction((float)$amount, $transaction, $iban);
        if (!$direction) {
            return 'direction not understood';
        }

        $currencyCode = (string)($transaction['transactionAmount']['currency'] ?? '');
        $currencyId   = $this->currencyIds[$currencyCode] ??= Currency::where('code', $currencyCode)->value('id');
        if (!$currencyId) {
            return 'unknown currency: '.$currencyCode;
        }

        $incoming        = $direction === BankTransactionDirectionEnum::IN;
        $counterAccount  = $this->text($transaction[$incoming ? 'debtorAccount' : 'creditorAccount']['iban'] ?? null);
        $counterpartName = $this->text($transaction[$incoming ? 'debtorName' : 'creditorName'] ?? null);
        $bookedAt        = Carbon::parse(substr($date, 0, 10), 'Europe/Bratislava');

        BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => $id,
            'provider_type'           => $counterAccount && empty($transaction['cardNumber']) ? 'TRANSFER' : 'OTHER',
            'direction'               => $direction,
            'amount'                  => round($incoming ? abs((float)$amount) : -abs((float)$amount), 2),
            'currency_id'             => $currencyId,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'counterparty_name'       => $counterpartName ?? $this->text($transaction['tradingPartyName'] ?? null),
            'counterparty_account'    => $counterAccount,
            'reference'               => $this->text($transaction['remittanceInformationUnstructured'] ?? null)
                ?? $this->text($transaction['variableSymbol'] ?? null)
                ?? $this->text($transaction['endToEndId'] ?? null),
            'description'             => $this->text($transaction['additionalInformation'] ?? null) ?? $this->text($transaction['bankTransactionCode'] ?? null),
            'booked_at'               => $bookedAt,
            'provider_updated_at'     => $bookedAt,
            'data'                    => ['transaction' => $transaction],
        ]);

        return true;
    }

    private function direction(float $amount, array $transaction, ?string $iban): ?BankTransactionDirectionEnum
    {
        if ($amount < 0) {
            return BankTransactionDirectionEnum::OUT;
        }

        if (!$iban) {
            return null;
        }
        $normalised = fn (mixed $value) => strtoupper(str_replace(' ', '', (string)$value));
        $toUs       = $normalised($transaction['creditorAccount']['iban'] ?? null) === $normalised($iban);
        $fromUs     = $normalised($transaction['debtorAccount']['iban'] ?? null) === $normalised($iban);

        return match (true) {
            $toUs && !$fromUs => BankTransactionDirectionEnum::IN,
            $fromUs && !$toUs => BankTransactionDirectionEnum::OUT,
            default => null,
        };
    }

    /**
     * @return array{currency: string|null, amount: float|int|string|null, taken_at: string}
     */
    private function balance(array $account): array
    {
        $balances = collect($account['balances'] ?? []);
        $balance  = $balances->firstWhere('balanceType', 'interimBooked')
            ?? $balances->firstWhere('balanceType', 'closingBooked')
            ?? $balances->first();

        return [
            'currency' => $balance['balanceAmount']['currency'] ?? ($account['accountReference']['currency'] ?? null),
            'amount'   => $balance['balanceAmount']['amount'] ?? null,
            'taken_at' => now()->toIso8601String(),
        ];
    }

    private function account(BankConnection $bankConnection, string $token): array
    {
        $accounts = collect($this->tatraGet($token, '/v3/accounts', ['withBalance' => 'true'])['accounts'] ?? []);

        $account = $bankConnection->external_id
            ? $accounts->firstWhere('accountId', $bankConnection->external_id)
            : ($accounts->count() === 1 ? $accounts->first() : null);

        if (!$account) {
            throw new RuntimeException(
                $bankConnection->external_id
                    ? "Tatra consent no longer reaches account $bankConnection->external_id"
                    : 'Tatra consent reaches '.$accounts->count().' accounts, set external_id to one of: '.$accounts->map(fn ($account) => ($account['accountId'] ?? '?').' '.($account['accountReference']['iban'] ?? '').' '.($account['accountReference']['currency'] ?? ''))->implode(', ')
            );
        }
        if (!empty($account['error']['errorCode'])) {
            throw new RuntimeException('Tatra account error: '.$account['error']['errorCode'].' '.($account['error']['errorDescription'] ?? ''));
        }

        return $account;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function transactions(string $token, string $accountId, string $from, string $to): array
    {
        $transactions = [];
        $response     = $this->tatraGet($token, "/v5/accounts/$accountId/transactions", [
            'dateFrom'      => $from,
            'dateTo'        => $to,
            'bookingStatus' => 'booked',
            'pageSize'      => self::PAGE_SIZE,
        ]);

        for ($pages = 1; ; $pages++) {
            $page = $response['transactions'] ?? [];
            array_push($transactions, ...$page);
            $next = $response['_links']['next']['href'] ?? null;
            if (!$next || !$page) {
                if (count($page) >= self::PAGE_SIZE) {
                    throw new RuntimeException("Tatra returned a full page of $accountId between $from and $to without a next link, the rest would be lost");
                }

                return $transactions;
            }
            if (!str_starts_with($next, self::BASE_URL.'/')) {
                throw new RuntimeException('Tatra next link leaves the API: '.mb_substr($next, 0, 200));
            }
            if ($pages >= 500) {
                throw new RuntimeException("Tatra transactions of $accountId did not end after $pages pages");
            }
            $response = $this->tatraGet($token, $next);
        }
    }

    public function accessToken(#[\SensitiveParameter] ?string $credentials): string
    {
        [$clientId, $clientSecret] = array_pad(explode(':', (string)$credentials, 2), 2, '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Tatra credentials must be "client_id:client_secret"');
        }

        $token = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->retry(3, fn (int $attempt) => $attempt * 2000, fn (\Throwable $exception) => $exception instanceof ConnectionException, throw: false)
            ->post(self::BASE_URL.'/auth/oauth/v2/token', [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'grant_type'    => 'client_credentials',
                'scope'         => 'PREMIUM_AIS',
            ])
            ->throw()
            ->json('access_token');

        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Tatra returned no access token');
        }

        return $token;
    }

    /**
     * @param string $path a path under the API, or the absolute "next" link of a previous page
     */
    private function tatraGet(#[\SensitiveParameter] string $token, string $path, array $query = []): ?array
    {
        return Http::withToken($token)
            ->acceptJson()
            ->withHeaders(['X-Request-ID' => (string)Str::uuid()])
            ->timeout(60)
            ->retry(
                3,
                fn (int $attempt, \Throwable $exception) => $exception instanceof RequestException && $exception->response->status() === 429 ? 30000 : $attempt * 2000,
                fn (\Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false
            )
            ->get(str_starts_with($path, 'http') ? $path : self::BASE_URL.$path, $query)
            ->throw()
            ->json();
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }
}

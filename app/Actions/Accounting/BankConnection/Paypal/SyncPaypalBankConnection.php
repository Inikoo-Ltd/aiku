<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection\Paypal;

use App\Actions\Accounting\BankPayee\ClassifyBankOutgoing;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Mirrors a PayPal business account into bank_transactions as one more bank: Checkout.com takes PayPal payments and
 * PayPal keeps the money in this account until it is withdrawn to our bank. Read only, through a REST app with the
 * Transaction Search feature; credentials are stored as "client_id:client_secret" and external_id is the PayPal account id.
 *
 * Checked on a year of real lines (24 Nov 2026): the balance moves by the amount plus the (negative) fee, so lines are
 * stored net. Only settled lines (status S) are kept, and never a hold or its release, whatever their status: a hold
 * and its release cancel out, and PayPal may settle one but not the other (a pending T1501 hold, a settled T1105
 * release), which would leave the lines short of the balance. Currency conversions (T02xx) and withdrawals to our bank (T04xx) are moves between our
 * own balances, and so are deposits from our bank (T03xx) and a withdrawal that bounced back (T1101); customer payments are labelled as PayPal payouts with PayPal's fee as the processor fee.
 */
class SyncPaypalBankConnection
{
    use AsAction;

    public const string BASE_URL = 'https://api-m.paypal.com';

    public const int FULL_HISTORY_DAYS = 3 * 365;

    public const int WINDOW_DAYS = 31;

    public const int OVERLAP_DAYS = 31;

    public const int PAGE_SIZE = 500;

    public const int MAX_ITEMS_PER_WINDOW = 10000;

    public const array HOLD_EVENT_CODES = ['T1105', 'T1110', 'T1111'];

    /** @var array<string, int|null> */
    private array $currencyIds = [];

    /**
     * @return array{synced: int, skipped: int, skipped_examples: list<string>}
     */
    public function handle(BankConnection $bankConnection, bool $full = false): array
    {
        $token    = $this->accessToken($bankConnection->credentials);
        $balances = $this->paypalGet($token, '/v1/reporting/balances', ['currency_code' => 'ALL']);
        $account  = $balances['account_id'] ?? null;
        if (!is_string($account) || ($bankConnection->external_id && $bankConnection->external_id !== $account)) {
            throw new RuntimeException("PayPal credentials reach account $account, not $bankConnection->external_id");
        }

        $bankConnection->update([
            'external_id' => $account,
            'sync_data'   => array_merge($bankConnection->sync_data, ['paypal_balances' => $this->balances($balances)]),
        ]);

        $now  = now()->utc()->startOfSecond();
        $from = ($full || !$bankConnection->last_synced_at)
            ? $now->copy()->subDays(self::FULL_HISTORY_DAYS)->addDay()
            : $bankConnection->last_synced_at->copy()->utc()->subDays(self::OVERLAP_DAYS);

        $synced          = 0;
        $skipped         = 0;
        $skippedExamples = [];
        for ($start = $from->copy(); $start < $now; $start->addDays(self::WINDOW_DAYS)) {
            $end          = $start->copy()->addDays(self::WINDOW_DAYS)->min($now);
            $transactions = $this->transactions($token, $start, $end);
            $existing     = BankTransaction::where('bank_connection_id', $bankConnection->id)
                ->whereIn('provider_transaction_id', array_filter(array_map(fn ($transaction) => $transaction['transaction_info']['transaction_id'] ?? null, $transactions)))
                ->pluck('provider_transaction_id')
                ->all();

            foreach ($transactions as $transaction) {
                $result = $this->insertTransaction($bankConnection, $transaction, $existing);
                if (is_string($result)) {
                    $skipped++;
                    if (count($skippedExamples) < 20) {
                        $skippedExamples[] = ($transaction['transaction_info']['transaction_id'] ?? '?').': '.$result;
                    }
                } elseif ($result) {
                    $synced++;
                    $existing[] = (string)$transaction['transaction_info']['transaction_id'];
                }
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'skipped_examples' => $skippedExamples];
    }

    /**
     * A withdrawal from PayPal arrives at our bank as an incoming transfer naming PayPal's bank reference (Wise SK shows
     * "YYW1051189730993/PAYPAL/YYW1051189730993"). Both legs are a move between our own balances, so the bank leg is
     * marked as one too, or cash flow and the review queue would count it as money received. Run after every bank sync,
     * as a bank resync may give the line its transfer type back.
     *
     * @return int bank lines marked
     */
    public static function markWithdrawalArrivals(Group $group): int
    {
        $marked = 0;
        BankTransaction::query()
            ->join('bank_connections', 'bank_connections.id', 'bank_transactions.bank_connection_id')
            ->where('bank_transactions.group_id', $group->id)
            ->where('bank_connections.provider', BankConnectionProviderEnum::PAYPAL)
            ->where('bank_transactions.provider_type', ClassifyBankOutgoing::OWN_BALANCE_TYPE)
            ->where('bank_transactions.direction', BankTransactionDirectionEnum::OUT)
            ->whereRaw("bank_transactions.data->'transaction'->'transaction_info'->>'bank_reference_id' ~ '^[A-Za-z0-9]{8,}$'")
            ->selectRaw("bank_transactions.id, bank_transactions.data->'transaction'->'transaction_info'->>'bank_reference_id' as bank_reference_id")
            ->toBase()
            ->get()
            ->each(function (object $withdrawal) use ($group, &$marked) {
                $marked += BankTransaction::where('group_id', $group->id)
                    ->where('direction', BankTransactionDirectionEnum::IN)
                    ->where('provider_type', 'TRANSFER')
                    ->whereNull('payment_id')
                    ->where('reference', 'ilike', '%'.$withdrawal->bank_reference_id.'%')
                    ->update([
                        'provider_type' => ClassifyBankOutgoing::OWN_BALANCE_TYPE,
                        'match_state'   => null,
                        'order_id'      => null,
                        'matched_at'    => null,
                        'match_data'    => json_encode(['paypal_withdrawal_id' => $withdrawal->id]),
                    ]);
            });

        return $marked;
    }

    /**
     * @param list<string> $existing
     *
     * @return bool|string true written, false already stored or not settled, a string says why the line was not understood
     */
    private function insertTransaction(BankConnection $bankConnection, array $transaction, array $existing): bool|string
    {
        $info = $transaction['transaction_info'] ?? [];
        $id   = $info['transaction_id'] ?? null;
        if (!is_string($id) || $id === '') {
            return 'no transaction id';
        }
        $code = (string)($info['transaction_event_code'] ?? '');
        if (in_array($id, $existing) || ($info['transaction_status'] ?? null) !== 'S' || in_array($code, self::HOLD_EVENT_CODES) || str_starts_with($code, 'T15')) {
            return false;
        }

        $gross = $info['transaction_amount']['value'] ?? null;
        $fee   = $info['fee_amount']['value'] ?? 0;
        $date  = $info['transaction_initiation_date'] ?? null;
        if (!is_numeric($gross) || !is_numeric($fee) || !is_string($date)) {
            return 'amount or date not understood';
        }
        $net = round((float)$gross + (float)$fee, 2);
        if ($net == 0.0) {
            return false;
        }

        $currencyCode = (string)($info['transaction_amount']['currency_code'] ?? '');
        $currencyId   = $this->currencyIds[$currencyCode] ??= Currency::where('code', $currencyCode)->value('id');
        if (!$currencyId) {
            return 'unknown currency: '.$currencyCode;
        }

        $direction      = $net > 0 ? BankTransactionDirectionEnum::IN : BankTransactionDirectionEnum::OUT;
        $ownBalance     = in_array(substr($code, 0, 3), ['T02', 'T03', 'T04']) || $code === 'T1101';
        $customerPayment = $direction === BankTransactionDirectionEnum::IN && str_starts_with($code, 'T00');
        $payer          = $transaction['payer_info']['payer_name'] ?? [];
        $bookedAt       = Carbon::parse($date);

        BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => $id,
            'provider_type'           => $ownBalance ? ClassifyBankOutgoing::OWN_BALANCE_TYPE : $code,
            'direction'               => $direction,
            'amount'                  => $net,
            'fee_amount'              => !$customerPayment && (float)$fee < 0 ? abs((float)$fee) : null,
            'currency_id'             => $currencyId,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'counterparty_name'       => $this->text($payer['alternate_full_name'] ?? null)
                ?? $this->text(trim(($payer['given_name'] ?? '').' '.($payer['surname'] ?? '')))
                ?? $this->text($transaction['payer_info']['email_address'] ?? null),
            'reference'               => $this->text($info['invoice_id'] ?? null) ?? $this->text($info['bank_reference_id'] ?? null) ?? $this->text($info['custom_field'] ?? null),
            'description'             => $this->text($info['transaction_subject'] ?? null) ?? $this->text($info['transaction_note'] ?? null) ?? $code,
            'booked_at'               => $bookedAt,
            'provider_updated_at'     => isset($info['transaction_updated_date']) ? Carbon::parse($info['transaction_updated_date']) : $bookedAt,
            'match_state'             => $customerPayment ? BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT : null,
            'match_data'              => $customerPayment ? [
                'provider'       => 'paypal',
                'processor_fees' => round(abs((float)$fee), 2),
                'reconciled_at'  => $bookedAt->toIso8601String(),
            ] : [],
            'data'                    => ['transaction' => $transaction],
        ]);

        return true;
    }

    /**
     * @return array<string, array{amount: float, reserved_amount: float}>
     */
    private function balances(array $response): array
    {
        $balances = [];
        foreach ($response['balances'] ?? [] as $balance) {
            $code = $balance['currency'] ?? null;
            if (!is_string($code) || !is_numeric($balance['total_balance']['value'] ?? null)) {
                throw new RuntimeException('Unreadable PayPal balance: '.mb_substr(json_encode($balance), 0, 300));
            }
            $balances[$code] = [
                'amount'          => (float)$balance['total_balance']['value'],
                'reserved_amount' => (float)($balance['withheld_balance']['value'] ?? 0),
            ];
        }
        if (!$balances) {
            throw new RuntimeException('PayPal returned no balances');
        }

        return $balances;
    }

    /**
     * PayPal answers at most 31 days and 10,000 lines per search, so a window that reaches the cap fails the sync instead of losing lines.
     *
     * @return list<array<string, mixed>>
     */
    private function transactions(string $token, Carbon $start, Carbon $end): array
    {
        $transactions = [];
        for ($page = 1; ; $page++) {
            $response = $this->paypalGet($token, '/v1/reporting/transactions', [
                'start_date' => $start->toIso8601ZuluString(),
                'end_date'   => $end->toIso8601ZuluString(),
                'fields'     => 'transaction_info,payer_info',
                'page_size'  => self::PAGE_SIZE,
                'page'       => $page,
            ]);
            if (($response['total_items'] ?? 0) >= self::MAX_ITEMS_PER_WINDOW) {
                throw new RuntimeException("PayPal has {$response['total_items']} lines between {$start->toIso8601ZuluString()} and {$end->toIso8601ZuluString()}, more than one search returns");
            }
            array_push($transactions, ...($response['transaction_details'] ?? []));
            if ($page >= ($response['total_pages'] ?? 0)) {
                return $transactions;
            }
        }
    }

    public function accessToken(#[\SensitiveParameter] ?string $credentials): string
    {
        [$clientId, $clientSecret] = array_pad(explode(':', (string)$credentials, 2), 2, '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal credentials must be "client_id:client_secret"');
        }

        $token = Http::asForm()
            ->acceptJson()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(30)
            ->retry(3, fn (int $attempt) => $attempt * 2000, fn (\Throwable $exception) => $exception instanceof ConnectionException, throw: false)
            ->post(self::BASE_URL.'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()
            ->json('access_token');

        if (!is_string($token) || $token === '') {
            throw new RuntimeException('PayPal returned no access token');
        }

        return $token;
    }

    private function paypalGet(#[\SensitiveParameter] string $token, string $path, array $query = []): array
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->retry(
                3,
                fn (int $attempt, \Throwable $exception) => $exception instanceof RequestException && $exception->response->status() === 429 ? 30000 : $attempt * 2000,
                fn (\Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false
            )
            ->get(self::BASE_URL.$path, $query)
            ->throw()
            ->json() ?? [];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }
}

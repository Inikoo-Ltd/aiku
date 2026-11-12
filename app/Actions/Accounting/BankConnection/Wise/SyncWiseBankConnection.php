<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection\Wise;

use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Currency;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mirrors a Wise business profile into bank_transactions. Wise only lets a personal token read the activity
 * feed (balance statements need SCA, which Wise no longer accepts by signature), so each activity becomes the
 * line(s) it moved on our balances, and transfers are enriched with their payment reference.
 *
 * Amounts on our side: incoming is primaryAmount (credited after Wise's fee); outgoing is secondaryAmount when
 * present (debited, fee included, in our currency) else primaryAmount; a conversion between our own balances
 * ("To USD") is two lines, secondaryAmount out of the source balance and primaryAmount into the target one.
 */
class SyncWiseBankConnection
{
    use AsAction;
    use WithWiseApi;

    private const string CONVERSION_CREDIT_SUFFIX = '#in';

    private const string JAR = 'jar';

    private const int PARSER_VERSION = 2;

    /** @var array<string, int|null> */
    private array $currencyIds = [];

    /**
     * Wise filters since/until on updatedOn, so an incremental run also picks up older lines whose status changed.
     *
     * @return array{synced: int, skipped: int, skipped_examples: list<string>}
     */
    public function handle(BankConnection $bankConnection, bool $full = false): array
    {
        $since = ($full || !$bankConnection->last_synced_at)
            ? null
            : $bankConnection->last_synced_at->copy()->subDay()->utc()->format('Y-m-d\TH:i:s.v\Z');

        $synced          = 0;
        $skipped         = 0;
        $skippedExamples = [];
        $cursor          = null;

        do {
            $page       = $this->wiseGet($bankConnection->credentials, "/v1/profiles/$bankConnection->external_id/activities", array_filter(['size' => 100, 'since' => $since, 'nextCursor' => $cursor]));
            $activities = $page['activities'] ?? [];

            $ids      = array_column($activities, 'id');
            $existing = BankTransaction::where('bank_connection_id', $bankConnection->id)
                ->whereIn('provider_transaction_id', array_merge($ids, array_map(fn ($id) => $id.self::CONVERSION_CREDIT_SUFFIX, $ids)))
                ->get()
                ->keyBy('provider_transaction_id');

            foreach ($activities as $activity) {
                $result = $this->upsertActivity($bankConnection, $activity, $existing);
                if (is_string($result)) {
                    $skipped++;
                    if (count($skippedExamples) < 20) {
                        $skippedExamples[] = $activity['id'].': '.$result;
                    }
                } elseif ($result) {
                    $synced++;
                }
            }

            $cursor = $page['cursor'] ?? null;
        } while ($cursor && $activities);

        return ['synced' => $synced, 'skipped' => $skipped, 'skipped_examples' => $skippedExamples];
    }

    /**
     * @param \Illuminate\Support\Collection<string, BankTransaction> $existing
     *
     * @return bool|string true written, false unchanged, a string says why the activity was not understood
     */
    private function upsertActivity(BankConnection $bankConnection, array $activity, $existing): bool|string
    {
        $stored = $existing->get($activity['id']);
        if ($stored && ($stored->data['activity']['updatedOn'] ?? null) === $activity['updatedOn'] && ($stored->data['parser_version'] ?? null) === self::PARSER_VERSION) {
            return false;
        }

        $legs = $this->legs($activity);
        if (is_string($legs)) {
            return $legs;
        }

        $currencyIds = [];
        foreach ($legs as $suffix => [, , $currencyCode]) {
            $currencyIds[$suffix] = $this->currencyIds[$currencyCode] ??= Currency::where('code', $currencyCode)->value('id');
            if (!$currencyIds[$suffix]) {
                return 'unknown currency: '.$currencyCode;
            }
        }

        $transfer = $stored?->data['transfer'] ?? null;
        if (!$transfer && ($activity['resource']['type'] ?? null) === 'TRANSFER') {
            $transfer = $this->fetchTransfer($bankConnection, $activity['resource']['id']);
        }

        DB::transaction(fn () => $this->writeLegs($bankConnection, $activity, $legs, $currencyIds, $transfer));

        return true;
    }

    private function writeLegs(BankConnection $bankConnection, array $activity, array $legs, array $currencyIds, ?array $transfer): void
    {
        BankTransaction::where('bank_connection_id', $bankConnection->id)
            ->whereIn('provider_transaction_id', array_map(
                fn ($suffix) => $activity['id'].$suffix,
                array_diff(['', self::CONVERSION_CREDIT_SUFFIX], array_keys($legs))
            ))
            ->delete();

        foreach ($legs as $suffix => $leg) {
            [$direction, $value] = $leg;
            $values = [
                    'group_id'            => $bankConnection->group_id,
                    'organisation_id'     => $bankConnection->organisation_id,
                    'provider_type'       => $activity['type'] ?? null,
                    'direction'           => $direction,
                    'amount'              => $direction === BankTransactionDirectionEnum::OUT ? -$value : $value,
                    'currency_id'         => $currencyIds[$suffix],
                    'account_reference'   => $leg[3] ?? null,
                    'status'              => match ($activity['status'] ?? null) {
                        'COMPLETED' => BankTransactionStatusEnum::BOOKED,
                        'CANCELLED' => BankTransactionStatusEnum::CANCELLED,
                        default     => BankTransactionStatusEnum::PENDING,
                    },
                    'counterparty_name'   => $this->plainText($activity['title'] ?? null),
                    'reference'           => $this->plainText($transfer['reference'] ?? $transfer['details']['reference'] ?? null),
                    'description'         => $this->plainText($activity['description'] ?? null),
                    'booked_at'           => Carbon::parse($activity['createdOn']),
                    'provider_updated_at' => Carbon::parse($activity['updatedOn']),
                    'data'                => array_filter(['activity' => $activity, 'transfer' => $transfer, 'parser_version' => self::PARSER_VERSION]),
            ];

            $stored = BankTransaction::where('bank_connection_id', $bankConnection->id)->where('provider_transaction_id', $activity['id'].$suffix)->first();
            if ($stored) {
                $stored->update(array_merge($values, $this->decisionNoLongerValid($stored, $values) ? self::FORGET_DECISION : []));
            } else {
                BankTransaction::create(array_merge($values, [
                    'bank_connection_id'      => $bankConnection->id,
                    'provider_transaction_id' => $activity['id'].$suffix,
                ]));
            }
        }
    }

    private const array FORGET_DECISION = ['match_state' => null, 'order_id' => null, 'match_data' => [], 'matched_at' => null];

    /**
     * A matching decision is taken on the line as it was; when the bank changes what matters, it is taken again, unless the
     * line already paid something.
     */
    private function decisionNoLongerValid(BankTransaction $stored, array $values): bool
    {
        if ($stored->match_state === null || $stored->payment_id !== null) {
            return false;
        }

        return $stored->status !== $values['status']
            || $stored->currency_id !== $values['currency_id']
            || $stored->reference !== $values['reference']
            || abs((float)$stored->amount - (float)$values['amount']) > 0.001;
    }

    /**
     * A move between a balance and its savings jar ("To Savings", or "To GBP" back out of it) is same-currency and carries no
     * secondaryAmount; it is two lines like a conversion, the jar side marked with account_reference "jar".
     *
     * @return array<string, array{0: BankTransactionDirectionEnum, 1: float, 2: string, 3?: string|null}>|string keyed by provider id suffix
     */
    public function legs(array $activity): array|string
    {
        $primary = $this->parseAmount($activity['primaryAmount'] ?? '');
        if (!$primary) {
            return 'amount not understood: '.($activity['primaryAmount'] ?? '');
        }

        $secondary = null;
        if (trim(strip_tags($activity['secondaryAmount'] ?? '')) !== '') {
            $secondary = $this->parseAmount($activity['secondaryAmount']);
            if (!$secondary) {
                return 'secondary amount not understood: '.$activity['secondaryAmount'];
            }
        }

        [$incoming, $primaryValue, $primaryCurrency] = $primary;

        if (($activity['type'] ?? null) === 'INTERBALANCE') {
            if ($secondary) {
                return [
                    ''                             => [BankTransactionDirectionEnum::OUT, $secondary[1], $secondary[2]],
                    self::CONVERSION_CREDIT_SUFFIX => [BankTransactionDirectionEnum::IN, $primaryValue, $primaryCurrency],
                ];
            }

            $title = (string)$this->plainText($activity['title'] ?? null);
            if (!str_starts_with($title, 'To ') || $incoming) {
                return 'balance move not understood: '.$title;
            }
            $intoJar = $title !== 'To '.$primaryCurrency;

            return [
                ''                             => [BankTransactionDirectionEnum::OUT, $primaryValue, $primaryCurrency, $intoJar ? null : self::JAR],
                self::CONVERSION_CREDIT_SUFFIX => [BankTransactionDirectionEnum::IN, $primaryValue, $primaryCurrency, $intoJar ? self::JAR : null],
            ];
        }

        if ($incoming) {
            return ['' => [BankTransactionDirectionEnum::IN, $primaryValue, $primaryCurrency]];
        }

        return ['' => [BankTransactionDirectionEnum::OUT, $secondary[1] ?? $primaryValue, $secondary[2] ?? $primaryCurrency]];
    }

    /**
     * Wise sends English display strings such as "<positive>+ 1,312,516 HUF</positive>" or "11.99 EUR"; anything else is refused
     * rather than guessed.
     *
     * @return array{0: bool, 1: float, 2: string}|null incoming, value, currency code
     */
    public function parseAmount(string $displayAmount): ?array
    {
        $text = trim(html_entity_decode(strip_tags($displayAmount)));
        if (!preg_match('/^(\+)?\s*((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?)\s+([A-Z]{3})$/u', $text, $matches)) {
            return null;
        }

        return [
            $matches[1] === '+',
            round((float)str_replace(',', '', $matches[2]), 2),
            $matches[3],
        ];
    }

    private function fetchTransfer(BankConnection $bankConnection, string $transferId): ?array
    {
        try {
            return $this->wiseGet($bankConnection->credentials, '/v1/transfers/'.$transferId);
        } catch (RequestException $exception) {
            if (in_array($exception->response->status(), [403, 404])) {
                return null;
            }
            throw $exception;
        }
    }

    private function plainText(?string $value): ?string
    {
        $value = trim(html_entity_decode(strip_tags((string)$value)));

        return $value === '' ? null : $value;
    }
}

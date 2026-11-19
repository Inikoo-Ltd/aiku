<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\WithCheckoutCom;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Checkout\Reports\ReportsQuery;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * A Checkout.com payout line pays no order: it settles card payments already recorded on their orders. Checkout files,
 * for each payout, a "financial actions by payout id" report listing every capture, refund, chargeback and fee in it;
 * this reads that report and keeps on the line which of our payments it settled, what Checkout kept, and what the bank
 * kept on the way in. Nothing is paid or booked. Dry run unless --commit; --relabel also labels payout lines that were
 * decided before payouts were recognised.
 */
class ReconcileCheckoutPayouts
{
    use AsAction;
    use WithCheckoutCom;

    public string $commandSignature = 'bank-transactions:checkout-payouts {--commit} {--relabel}';

    /** @var list<array<string, mixed>> what the first reports read looked like, for the command to show */
    public array $samples = [];

    /**
     * @return array<string, int>
     */
    public function handle(bool $commit, bool $relabel = false): array
    {
        $counts = [];
        if ($relabel) {
            $this->relabel($commit, $counts);
        }

        BankTransaction::where('match_state', BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
            ->whereNull('payment_id')
            ->whereNull('match_data->reconciled_at')
            ->where(fn ($query) => $query->whereNull('match_data->provider')->orWhere('match_data->provider', 'checkout'))
            ->where('booked_at', '>=', now()->subDays(120))
            ->get()
            ->groupBy('organisation_id')
            ->each(function (Collection $bankTransactions, int $organisationId) use ($commit, &$counts) {
                foreach ($this->credentials($organisationId) as [$publicKey, $secretKey]) {
                    $bankTransactions = $bankTransactions->filter(fn (BankTransaction $bankTransaction) => empty($bankTransaction->match_data['reconciled_at']));
                    if ($bankTransactions->isEmpty()) {
                        return;
                    }
                    try {
                        $files = $this->payoutReportFiles($publicKey, $secretKey, $bankTransactions->min('booked_at')->copy()->subDays(10), now());
                    } catch (Throwable $exception) {
                        Sentry::captureException($exception);
                        $counts['report list failed'] = ($counts['report list failed'] ?? 0) + 1;
                        continue;
                    }

                    foreach ($bankTransactions as $bankTransaction) {
                        $file = $files[$bankTransaction->match_data['payout_id'] ?? ''] ?? null;
                        if (!$file) {
                            continue;
                        }
                        try {
                            $report = $this->parsePayoutReport($this->payoutReportCsv($publicKey, $secretKey, $file['report_id'], $file['file_id']));
                        } catch (Throwable $exception) {
                            Sentry::captureException($exception);
                            $counts['report read failed'] = ($counts['report read failed'] ?? 0) + 1;
                            continue;
                        }
                        $understood = $this->addsUp($bankTransaction, $report);
                        if (count($this->samples) < 3) {
                            $this->samples[] = ['payout' => $bankTransaction->match_data['payout_id'], 'received' => (float)$bankTransaction->amount, 'understood' => $understood] + Arr::only($report, ['header', 'total', 'by_type']);
                        }
                        if (!$understood) {
                            $counts['reports not understood'] = ($counts['reports not understood'] ?? 0) + 1;
                            continue;
                        }
                        $matchData = $this->reconcile($bankTransaction, $report);
                        $counts['payouts reconciled'] = ($counts['payouts reconciled'] ?? 0) + 1;
                        if ($matchData['payments_missing']) {
                            $counts['payouts with payments not in aiku'] = ($counts['payouts with payments not in aiku'] ?? 0) + 1;
                        }
                        $bankTransaction->match_data = $matchData;
                        if ($commit) {
                            $bankTransaction->update(['match_data' => $matchData]);
                        }
                    }
                }
            });

        $counts['payouts without a report yet'] = BankTransaction::where('match_state', BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
            ->whereNull('payment_id')
            ->whereNull('match_data->reconciled_at')
            ->where(fn ($query) => $query->whereNull('match_data->provider')->orWhere('match_data->provider', 'checkout'))
            ->count();

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     */
    private function relabel(bool $commit, array &$counts): void
    {
        $matcher = MatchBankTransactions::make();

        BankTransaction::where('direction', BankTransactionDirectionEnum::IN)
            ->where('status', BankTransactionStatusEnum::BOOKED)
            ->whereIn('provider_type', MatchBankTransactions::CUSTOMER_TRANSFER_TYPES)
            ->whereNull('payment_id')
            ->where(fn ($query) => $query->whereNull('match_state')->orWhereIn('match_state', [BankTransactionMatchStateEnum::UNMATCHED, BankTransactionMatchStateEnum::REVIEW, BankTransactionMatchStateEnum::DISMISSED]))
            ->where('counterparty_name', 'ilike', 'checkout sas%')
            ->chunkById(500, function ($bankTransactions) use ($matcher, $commit, &$counts) {
                foreach ($bankTransactions as $bankTransaction) {
                    $payoutId = $matcher->cardProcessorPayoutId($bankTransaction);
                    if (!$payoutId) {
                        $counts['checkout lines without a payout id'] = ($counts['checkout lines without a payout id'] ?? 0) + 1;
                        continue;
                    }
                    $counts['payout lines labelled'] = ($counts['payout lines labelled'] ?? 0) + 1;
                    if ($commit) {
                        $bankTransaction->update([
                            'match_state' => BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT,
                            'order_id'    => null,
                            'match_data'  => ['provider' => 'checkout', 'payout_id' => $payoutId],
                            'matched_at'  => now(),
                        ]);
                    }
                }
            });
    }

    /**
     * A report is only trusted when it explains the line: some captures were read, and what it pays out is what the
     * bank received plus at most the bank's fee on the way in (2%, or the connection's fee ceiling on small payouts).
     *
     * @param array<string, mixed> $report
     */
    private function addsUp(BankTransaction $bankTransaction, array $report): bool
    {
        $bankFee = round($report['total'] - (float)$bankTransaction->amount, 2);

        return $report['captured'] !== []
            && $bankFee >= -0.01
            && $bankFee <= max(round($report['total'] * 0.02, 2), MatchBankTransactions::make()->feeTolerance(PHP_INT_MAX, $bankTransaction));
    }

    /**
     * Payment accounts of one Checkout merchant share its keys, so each pair is asked once. The payments key cannot read
     * reports, so when any account of the organisation has credentials.reports_public_key / reports_secret_key (a key with
     * the Reports scope) only those are asked.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function credentials(int $organisationId): array
    {
        if (!app()->environment('production')) {
            return [[(string)config('app.sandbox.checkout_com.public_key'), (string)config('app.sandbox.checkout_com.secret_key')]];
        }

        $paymentAccounts = PaymentAccount::where('organisation_id', $organisationId)->where('type', PaymentAccountTypeEnum::CHECKOUT)->get();
        $keyPairs        = fn (string $prefix) => $paymentAccounts
            ->map(fn (PaymentAccount $paymentAccount) => [(string)Arr::get($paymentAccount->data, "credentials.{$prefix}public_key"), (string)Arr::get($paymentAccount->data, "credentials.{$prefix}secret_key")])
            ->filter(fn (array $keys) => $keys[0] !== '' && $keys[1] !== '')
            ->unique(fn (array $keys) => $keys[1])
            ->values();
        $reportsKeys = $keyPairs('reports_');

        return ($reportsKeys->isNotEmpty() ? $reportsKeys : $keyPairs(''))->all();
    }

    /**
     * @return array<string, array{report_id: string, file_id: string}> keyed by payout id
     */
    public function payoutReportFiles(string $publicKey, string $secretKey, DateTime $from, DateTime $to): array
    {
        $reportsClient = $this->checkoutApi($publicKey, $secretKey)->getReportsClient();
        $files         = [];
        $query                = new ReportsQuery();
        $query->created_after = $from;
        $query->created_before = $to;
        $query->limit         = 100;

        do {
            $response = $reportsClient->getAllReports($query);
            foreach ($response['data'] ?? [] as $report) {
                $payoutId = $this->payoutIdTag($report);
                $csvFile  = collect($report['files'] ?? [])->first(fn ($file) => strcasecmp((string)($file['format'] ?? ''), 'CSV') === 0);
                if (($report['type'] ?? null) === 'FinancialActionsByPayout' && $payoutId && $csvFile) {
                    $files[$payoutId] = ['report_id' => $report['id'], 'file_id' => $csvFile['id']];
                }
            }
            parse_str((string)parse_url((string)($response['_links']['next']['href'] ?? ''), PHP_URL_QUERY), $next);
            $query->pagination_token = $next['pagination_token'] ?? null;
        } while ($query->pagination_token);

        return $files;
    }

    /**
     * @param array<string, mixed> $report
     */
    public function payoutIdTag(array $report): ?string
    {
        foreach ($report['tags'] ?? [] as $tag) {
            if (preg_match('/^payout_id:([0-9a-z]{12})$/i', (string)$tag, $matches)) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    public function payoutReportCsv(string $publicKey, string $secretKey, string $reportId, string $fileId): string
    {
        $response = $this->checkoutApi($publicKey, $secretKey)->getReportsClient()->getReportFile($reportId, $fileId);
        if (!isset($response['contents'])) {
            throw new \RuntimeException('The payout report did not come back as CSV: '.mb_substr(json_encode(Arr::except($response, ['http_metadata'])), 0, 300));
        }

        return (string)$response['contents'];
    }

    private function checkoutApi(string $publicKey, string $secretKey): \Checkout\CheckoutApi
    {
        return $this->getCheckoutApi($publicKey, $secretKey) ?? throw new \RuntimeException('Checkout.com rejected the keys');
    }

    /**
     * @return array{header: list<string>, total: float, currency: string|null, by_type: array<string, float>, captured: array<string, float>, refunded: array<string, float>}
     */
    public function parsePayoutReport(string $csv): array
    {
        $rows   = array_map(fn (string $row) => str_getcsv($row, ',', '"', ''), preg_split('/\r\n|\n|\r/', trim($csv)));
        $header = array_map('trim', array_shift($rows) ?? []);
        if (!in_array('Holding Currency Amount', $header, true)) {
            throw new \RuntimeException('Not a financial actions by payout id report');
        }

        $report = ['header' => $header, 'total' => 0.0, 'currency' => null, 'by_type' => [], 'captured' => [], 'refunded' => []];
        foreach ($rows as $row) {
            if (count($row) !== count($header)) {
                continue;
            }
            $line   = array_combine($header, $row);
            $amount = (float)$line['Holding Currency Amount'];
            $action = trim($line['Action Type'] ?? '');
            $type   = $action.' / '.trim($line['Breakdown Type'] ?? '');

            $report['total']             += $amount;
            $report['currency']          ??= $line['Holding Currency'] ?? null;
            $report['by_type'][$type]    = ($report['by_type'][$type] ?? 0) + $amount;

            $paymentId = trim($line['Payment ID'] ?? '');
            $breakdown = trim($line['Breakdown Type'] ?? '');
            if ($paymentId) {
                if (preg_match('/^(partial )?capture$/i', $action) && strcasecmp($breakdown, 'Capture') === 0) {
                    $report['captured'][$paymentId] = ($report['captured'][$paymentId] ?? 0) + (float)($line['Processing Currency Amount'] ?? $amount);
                } elseif (in_array(strtolower($action), ['refund', 'partial refund'], true) && strcasecmp($breakdown, 'Refund') === 0) {
                    $report['refunded'][$paymentId] = ($report['refunded'][$paymentId] ?? 0) + (float)($line['Processing Currency Amount'] ?? $amount);
                }
            }
        }
        $report['total']   = round($report['total'], 2);
        $report['by_type'] = array_map(fn ($amount) => round($amount, 2), $report['by_type']);

        return $report;
    }

    /**
     * @param array{header: list<string>, total: float, currency: string|null, by_type: array<string, float>, captured: array<string, float>, refunded: array<string, float>} $report
     *
     * @return array<string, mixed>
     */
    public function reconcile(BankTransaction $bankTransaction, array $report): array
    {
        $payments = Payment::where('organisation_id', $bankTransaction->organisation_id)
            ->whereIn('reference', array_keys($report['captured']))
            ->whereHas('paymentAccount', fn ($query) => $query->where('type', PaymentAccountTypeEnum::CHECKOUT))
            ->get(['id', 'reference', 'amount', 'payment_account_id']);

        $gross      = round(array_sum(array_filter($report['by_type'], fn ($amount, $type) => !preg_match('/fee$/i', $type), ARRAY_FILTER_USE_BOTH)), 2);
        $fees       = round($gross - $report['total'], 2);
        $received   = round((float)$bankTransaction->amount, 2);
        $missing    = array_values(array_diff(array_keys($report['captured']), $payments->pluck('reference')->all()));

        return array_merge($bankTransaction->match_data, [
            'reason'             => null,
            'reconciled_at'      => now()->toIso8601String(),
            'payout_total'       => $report['total'],
            'gross'              => $gross,
            'processor_fees'     => $fees,
            'bank_fee'           => round($report['total'] - $received, 2),
            'by_type'            => $report['by_type'],
            'payment_ids'        => $payments->pluck('id')->all(),
            'payments_in_payout' => count($report['captured']),
            'refunds_in_payout'  => count($report['refunded']),
            'payments_missing'   => array_slice($missing, 0, 50),
            'payment_account_id' => $payments->countBy('payment_account_id')->sortDesc()->keys()->first(),
        ]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $commit = (bool)$command->option('commit');
        foreach ($this->handle($commit, (bool)$command->option('relabel')) as $outcome => $count) {
            $command->info("$outcome: $count");
        }
        foreach ($this->samples as $sample) {
            $command->line(json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        if (!$commit) {
            $command->warn('Dry run: nothing written, run again with --commit');
        }

        return 0;
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Ordering\Order\PayOrder;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\Shipment;
use App\Models\Ordering\Order;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Carriers pay the cash they collected on delivery in one transfer for many parcels, and name only a few of them in
 * the bank reference. Each carrier emails the full list to the shop mailbox the day after: tracking number, our order
 * number and the amount, adding up to the transfer. This reads those lists, links the line to the cash on delivery
 * payment of every parcel in it, and records the payment on orders that do not have it yet (what staff used to type
 * from the list), only for lines booked after auto-pay was switched on. The bank line is what authorises the money: a
 * list is only used when a carrier transfer of exactly its total arrived. A line stays open, and is read again on the
 * next run, while an order in its list still has no cash on delivery payment. Dry run unless --commit.
 */
class ReconcileCodPayouts
{
    use AsAction;

    public string $commandSignature = 'bank-transactions:cod-payouts {--commit} {--days=120}';

    /**
     * Carriers paying cash on delivery, by the payer name on the transfer. Their list is found in the shop mailboxes by
     * the Gmail query and the attachment name: GLS sends its lists to a staff mailbox and staff forward them to the
     * shop mailboxes, so GLS Hungary is only reconciled once someone forwarded the list. A parcel may differ from its
     * order by the tolerance: DPD Hungary rounds forints to the unit, GLS Hungary to 5 forints as cash is.
     */
    public const array CARRIERS = [
        'dpd_hu' => ['payer' => '/^dpd hung(ary|ária)/iu', 'name' => 'DPD Hungary', 'query' => 'from:noreply@dpd.hu', 'file' => '/\.pdf$/i', 'tolerance' => 0.51],
        'dpd_pl' => ['payer' => '/^dpd polska\b/i', 'name' => 'DPD Poland', 'query' => 'filename:"COD specification"', 'file' => '/^COD specification.*\.xlsx$/i', 'tolerance' => 0.01],
        'gls_hu' => ['payer' => '/^gls general log\.?\s*systems hung/i', 'name' => 'GLS Hungary', 'query' => 'filename:980010059', 'file' => '/_HUF_.*\.xlsx$/i', 'tolerance' => 2.51],
        'gls_ro' => ['payer' => '/^gls general logistics systems roman/i', 'name' => 'GLS Romania', 'query' => 'filename:980010059', 'file' => '/_RON_.*\.xlsx$/i', 'tolerance' => 0.01],
    ];

    /** @var list<array<string, mixed>> */
    public array $samples = [];

    /** @var list<string> carrier attachments that could not be read as a list */
    public array $unreadable = [];

    public static function carrier(?string $counterpartyName): ?string
    {
        foreach (self::CARRIERS as $key => $carrier) {
            if (preg_match($carrier['payer'], (string)$counterpartyName)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    public function handle(bool $commit, int $days = 120): array
    {
        $counts = [];
        $count  = function (string $outcome) use (&$counts) {
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
        };

        $this->relabel($commit, $count);

        BankTransaction::where('match_state', BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
            ->where('match_data->provider', 'cod')
            ->whereNull('match_data->reconciled_at')
            ->where('booked_at', '>=', now()->subDays($days))
            ->with('bankConnection')
            ->get()
            ->filter(fn (BankTransaction $bankTransaction) => isset(self::CARRIERS[$bankTransaction->match_data['carrier']]))
            ->groupBy(fn (BankTransaction $bankTransaction) => $bankTransaction->organisation_id.'|'.$bankTransaction->match_data['carrier'])
            ->each(function (Collection $bankTransactions) use ($commit, $count, &$counts) {
                $first = $bankTransactions->first();
                $lists = $this->listsInMailboxes($first->organisation_id, $first->match_data['carrier'], $bankTransactions->min('booked_at')->copy()->subDays(2));
                foreach ($bankTransactions as $bankTransaction) {
                    $list = $this->listFor($bankTransaction, $lists);
                    if (!$list) {
                        $count('payouts without a list yet');
                        continue;
                    }
                    try {
                        $matchData = $this->reconcile($bankTransaction, $list, $commit);
                    } catch (Throwable $exception) {
                        Sentry::captureException($exception);
                        $count('payouts failed');
                        continue;
                    }
                    $count('payouts reconciled');
                    $counts['cod payments recorded'] = ($counts['cod payments recorded'] ?? 0) + $matchData['payments_created'];
                    $counts['cod payments not recorded'] = ($counts['cod payments not recorded'] ?? 0) + count($matchData['payments_not_recorded']);
                    if (count($this->samples) < 3) {
                        $this->samples[] = ['line' => $bankTransaction->id, 'received' => (float)$bankTransaction->amount] + array_intersect_key($matchData, array_flip(['list', 'payments_in_payout', 'payment_ids', 'payments_created', 'payments_not_recorded', 'payments_missing', 'differences']));
                    }
                    if ($commit) {
                        $bankTransaction->update(['match_data' => $matchData]);
                    }
                }
            });

        if ($this->unreadable) {
            $counts['carrier attachments not understood'] = count(array_unique($this->unreadable));
            Sentry::captureMessage('COD lists not understood: '.implode(', ', array_slice(array_unique($this->unreadable), 0, 10)));
        }

        return $counts;
    }

    /**
     * Lines from a carrier decided before carriers were recognised, or left unmatched, become cash on delivery payouts.
     */
    private function relabel(bool $commit, callable $count): void
    {
        BankTransaction::where('direction', BankTransactionDirectionEnum::IN)
            ->where('status', BankTransactionStatusEnum::BOOKED)
            ->whereNull('payment_id')
            ->where(fn ($query) => $query->whereNull('match_state')->orWhere('match_state', BankTransactionMatchStateEnum::UNMATCHED))
            ->where(fn ($query) => $query->where('counterparty_name', 'ilike', 'dpd %')->orWhere('counterparty_name', 'ilike', 'gls %'))
            ->eachById(function (BankTransaction $bankTransaction) use ($commit, $count) {
                $carrier = self::carrier($bankTransaction->counterparty_name);
                if (!$carrier) {
                    return;
                }
                $count('payout lines labelled');
                if ($commit) {
                    $bankTransaction->update([
                        'match_state' => BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT,
                        'order_id'    => null,
                        'match_data'  => ['provider' => 'cod', 'carrier' => $carrier],
                        'matched_at'  => now(),
                    ]);
                }
            });
    }

    /**
     * Every list the carrier sent to any shop mailbox of the organisation since $since, once each: the same list is
     * often delivered twice or forwarded. Two different lists of the same date and total are both kept, so neither is
     * used.
     *
     * @return array<string, array{date: string, total: float, rows: list<array{tracking: string, reference: string, amount: float}>, source: string}>
     */
    public function listsInMailboxes(int $organisationId, string $carrier, \DateTimeInterface $since): array
    {
        $lists = [];
        $query = self::CARRIERS[$carrier]['query'];
        foreach (Shop::where('organisation_id', $organisationId)->get() as $shop) {
            $client = GmailClient::isShopMailboxUsable($shop) ? GmailClient::forShop($shop) : null;
            if (!$client) {
                continue;
            }
            try {
                $pageToken = null;
                do {
                    $page = $client->listMessageIds("$query has:attachment after:".$since->format('Y/m/d'), $pageToken);
                    foreach ($page['ids'] as $messageId) {
                        $raw = $client->getMessage($messageId);
                        foreach (GmailMessageParser::attachments($raw['payload'] ?? []) as $attachment) {
                            if (!preg_match(self::CARRIERS[$carrier]['file'], $attachment['filename'])) {
                                continue;
                            }
                            $content = $attachment['attachmentId'] ? $client->getAttachment($messageId, $attachment['attachmentId']) : GmailMessageParser::decodeData((string)$attachment['data']);
                            try {
                                $list = $this->parse($carrier, $content);
                            } catch (Throwable $exception) {
                                Sentry::captureException($exception);
                                $list = null;
                            }
                            if (!$list) {
                                $this->unreadable[] = $shop->slug.':'.$messageId.':'.$attachment['filename'];
                                continue;
                            }
                            $lists[$list['date'].'|'.$list['total'].'|'.md5(json_encode($list['rows']))] ??= $list + ['source' => $shop->slug.':'.$messageId];
                        }
                    }
                    $pageToken = $page['next'];
                } while ($pageToken);
            } catch (Throwable $exception) {
                Sentry::captureException($exception);
            }
        }

        return $lists;
    }

    /**
     * @return array{date: string, total: float, rows: list<array{tracking: string, reference: string, amount: float}>}|null
     */
    public function parse(string $carrier, string $content): ?array
    {
        if ($carrier === 'dpd_hu') {
            return $this->parseDpdHungaryList((new Parser())->parseContent($content)->getText());
        }

        $file = tempnam(sys_get_temp_dir(), 'cod');
        try {
            file_put_contents($file, $content);
            $rows = IOFactory::load($file)->getActiveSheet()->toArray(null, false, false);
        } finally {
            unlink($file);
        }

        return $carrier === 'dpd_pl' ? $this->parseDpdPolandList($rows) : $this->parseGlsList($rows);
    }

    /**
     * DPD Hungary's "Átutalás melléklet lista": a date, one row per parcel and the total. In the PDF's text each
     * parcel starts with its 14 digit number and ends on a line where the amount (one decimal) runs into our order
     * number, sometimes followed by " / <prefix><number>" (and "-1" on an order's second parcel). The list is only
     * read when its rows add up to its total.
     *
     * @return array{date: string, total: int, rows: list<array{tracking: string, reference: string, amount: float}>}|null
     */
    public function parseDpdHungaryList(string $text): ?array
    {
        if (!preg_match('/Dátum:\s*(\d{4}-\d{2}-\d{2})/u', $text, $date) || !preg_match('/Összesen:\s*([\d,. ]+)/u', $text, $total)) {
            return null;
        }
        preg_match_all('/^(\d{14})\s/m', $text, $trackings);
        preg_match_all('/^(\d+\.\d)(\d{1,8})(?:\s*\/.*)?\s*$/m', $text, $amounts);
        if (!$trackings[1] || count($trackings[1]) !== count($amounts[1])) {
            return null;
        }

        $rows = [];
        foreach ($trackings[1] as $i => $tracking) {
            $rows[] = ['tracking' => $tracking, 'reference' => $amounts[2][$i], 'amount' => (float)$amounts[1][$i]];
        }
        $total = (int)preg_replace('/\D/', '', $total[1]);

        return round(array_sum(array_column($rows, 'amount'))) == $total
            ? ['date' => $date[1], 'total' => $total, 'rows' => $rows]
            : null;
    }

    /**
     * GLS Hungary's "Utánvét részletező" and GLS Romania's "Lista Colete cu Ramburs" sheets: the transfer date in the
     * header ("Utalás dátuma: 2026. 11. 10." or "Data tranferarii banilor: 13.11.2026"), one row per parcel (report
     * number, 11 digit parcel number, our order number with "-1"/"/1" on an order's further parcels, delivery date,
     * amount) and a last row holding only the total. The list is only read when its rows add up to its total.
     *
     * @param list<list<mixed>> $rows
     *
     * @return array{date: string, total: float, rows: list<array{tracking: string, reference: string, amount: float}>}|null
     */
    public function parseGlsList(array $rows): ?array
    {
        $date   = null;
        $total  = null;
        $parcels = [];
        foreach ($rows as $row) {
            $first = trim((string)($row[0] ?? ''));
            if (preg_match('/(\d{4})\.\s*(\d{2})\.\s*(\d{2})\.?\s*$/', $first, $match) || preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s*$/', $first, $match)) {
                $date = strlen($match[1]) === 4 ? "$match[1]-$match[2]-$match[3]" : "$match[3]-$match[2]-$match[1]";
            } elseif (preg_match('/^\d{11}$/', trim((string)($row[1] ?? ''))) && is_numeric($row[4] ?? null)) {
                $parcels[] = ['tracking' => trim((string)$row[1]), 'reference' => preg_replace('/\D/', '', preg_replace('/[-\/]\d+$/', '', trim((string)$row[2]))), 'amount' => (float)$row[4]];
            } elseif ($first === '' && trim((string)($row[1] ?? '')) === '' && is_numeric($row[4] ?? null)) {
                $total = round((float)$row[4], 2);
            }
        }

        return $date && $parcels && $total !== null && round(array_sum(array_column($parcels, 'amount')), 2) == $total
            ? ['date' => $date, 'total' => $total, 'rows' => $parcels]
            : null;
    }

    /**
     * DPD Poland's "COD specification" sheet, sent by DPD Slovakia: one row per parcel with our order number (without
     * its PL prefix) in "Customer number2" and the date of the bank transfer. Its "Total transfer amount" is not the
     * transfer (it adds up other customers), so the list carries no total of its own: its rows are only used when they
     * add up to a DPD Poland transfer to the grosz. A sheet holding more than one transfer date is not read.
     *
     * @param list<list<mixed>> $rows
     *
     * @return array{date: string, total: float, rows: list<array{tracking: string, reference: string, amount: float}>}|null
     */
    public function parseDpdPolandList(array $rows): ?array
    {
        $header = array_map(fn ($cell) => trim((string)$cell), (array)array_shift($rows));
        $column = array_flip($header);
        if (!isset($column['Parcel label number'], $column['COD amount in PLN'], $column['Customer number2'], $column['Bank transfer date'])) {
            return null;
        }

        $dates   = [];
        $parcels = [];
        foreach ($rows as $row) {
            $tracking = trim((string)($row[$column['Parcel label number']] ?? ''));
            if ($tracking === '') {
                continue;
            }
            $amount = $row[$column['COD amount in PLN']] ?? null;
            $date   = $row[$column['Bank transfer date']] ?? null;
            if (!preg_match('/^\d{14}$/', $tracking) || !is_numeric($amount) || !is_numeric($date)) {
                return null;
            }
            $dates[ExcelDate::excelToDateTimeObject($date)->format('Y-m-d')] = true;
            $parcels[] = ['tracking' => $tracking, 'reference' => preg_replace('/\D/', '', (string)$row[$column['Customer number2']]), 'amount' => (float)$amount];
        }

        return $parcels && count($dates) === 1
            ? ['date' => array_key_first($dates), 'total' => round(array_sum(array_column($parcels, 'amount')), 2), 'rows' => $parcels]
            : null;
    }

    /**
     * The one list of exactly this amount, dated within three days of the line, that no other line has claimed.
     *
     * @param array<string, array<string, mixed>> $lists
     *
     * @return array<string, mixed>|null
     */
    private function listFor(BankTransaction $bankTransaction, array $lists): ?array
    {
        $candidates = collect($lists)
            ->filter(fn (array $list) => round((float)$list['total'], 2) == round((float)$bankTransaction->amount, 2)
                && abs($bankTransaction->booked_at->copy()->startOfDay()->diffInDays(Carbon::parse($list['date']))) <= 3)
            ->reject(fn (array $list) => BankTransaction::where('id', '!=', $bankTransaction->id)
                ->where('match_data->provider', 'cod')
                ->where('match_data->list->date', $list['date'])
                ->whereRaw("(match_data->'list'->>'total')::numeric = ?", [$list['total']])
                ->exists());

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * Each parcel is followed to its order through its shipment, and must carry the order number the list gives; an
     * order sent in several parcels is paid what its parcels add up to. Its cash on delivery payment is linked; a payment staff typed that differs from the list by more than the rounding is
     * reported, never changed. An order without one gets it, for what it still owes, when that rounds to the list.
     *
     * @param array{date: string, total: int|float, rows: list<array{tracking: string, reference: string, amount: float}>, source?: string} $list
     *
     * @return array<string, mixed>
     */
    public function reconcile(BankTransaction $bankTransaction, array $list, bool $commit): array
    {
        $paymentIds  = [];
        $missing     = [];
        $notRecorded = [];
        $differences = [];
        $created     = 0;
        $autoPayFrom = $bankTransaction->bankConnection->settings['auto_pay_from'] ?? null;
        $mayPay      = $commit && $autoPayFrom && $bankTransaction->booked_at->gte($autoPayFrom);

        $parcels = [];
        foreach ($list['rows'] as $row) {
            $order = $this->order($bankTransaction->organisation_id, $row['tracking'], $row['reference']);
            if (!$order) {
                $missing[] = $row['tracking'];
                continue;
            }
            $parcels[$order->id]['order']    = $order;
            $parcels[$order->id]['amount']   = ($parcels[$order->id]['amount'] ?? 0) + $row['amount'];
            $parcels[$order->id]['count']    = ($parcels[$order->id]['count'] ?? 0) + 1;
            $parcels[$order->id]['tracking'] ??= $row['tracking'];
        }

        foreach ($parcels as ['order' => $order, 'amount' => $amount, 'count' => $parcelCount, 'tracking' => $tracking]) {
            $tolerance = (self::CARRIERS[$bankTransaction->match_data['carrier'] ?? '']['tolerance'] ?? 0.51) * $parcelCount;
            $payments  = $this->codPayments($order);
            if ($payments->isNotEmpty()) {
                array_push($paymentIds, ...$payments->pluck('id')->all());
                $recorded = round((float)$payments->sum('amount'), 2);
                if (abs($recorded - $amount) > $tolerance) {
                    $differences[] = ['order' => $order->reference, 'list' => $amount, 'aiku' => $recorded];
                }
                continue;
            }

            $owed       = round($order->amountStillToPay(), 2);
            $codAccount = $this->codAccount($order);
            if ($order->to_be_paid_by !== OrderToBePaidByEnum::CASH_ON_DELIVERY || $order->currency_id !== $bankTransaction->currency_id || !$codAccount || abs($owed - $amount) > $tolerance) {
                $differences[] = ['order' => $order->reference, 'list' => $amount, 'aiku' => $owed, 'not_recorded' => true];
                continue;
            }
            if (!$mayPay) {
                $notRecorded[] = $order->reference;
                continue;
            }
            $order = $order->fresh();
            $owed  = round($order->amountStillToPay(), 2);
            if ($this->codPayments($order)->isNotEmpty() || abs($owed - $amount) > $tolerance) {
                $notRecorded[] = $order->reference;
                continue;
            }
            $payment = PayOrder::make()->action($order, $codAccount, [
                'amount'    => $owed,
                'reference' => $tracking,
                'status'    => PaymentStatusEnum::SUCCESS,
                'state'     => PaymentStateEnum::COMPLETED,
            ]);
            $paymentIds[] = $payment->id;
            $created++;
        }

        return array_merge($bankTransaction->match_data ?? [], [
            'reason'             => null,
            'reconciled_at'      => $notRecorded || $missing || $differences ? null : now()->toIso8601String(),
            'list'               => ['date' => $list['date'], 'total' => $list['total'], 'source' => $list['source'] ?? null],
            'payout_total'       => round((float)$bankTransaction->amount, 2),
            'processor_fees'     => 0,
            'bank_fee'           => 0,
            'payment_ids'        => $paymentIds,
            'payments_in_payout' => count($list['rows']),
            'refunds_in_payout'  => 0,
            'payments_created'   => $created,
            'payments_missing'   => $missing,
            'payments_not_recorded' => $notRecorded,
            'differences'        => $differences,
        ]);
    }

    /**
     * The order sent with this parcel that carries the order number on the list. A shipment of several parcels keeps
     * their numbers in one string ("91130362231, 91130362250"), and a carrier sometimes reuses a number.
     */
    private function order(int $organisationId, string $tracking, string $reference): ?Order
    {
        $shipments = Shipment::where('organisation_id', $organisationId)->where('tracking', $tracking)->get();
        if ($shipments->isEmpty()) {
            $shipments = Shipment::where('organisation_id', $organisationId)->where('tracking', 'like', "%$tracking%")->get();
        }
        $orders = $shipments
            ->flatMap(fn (Shipment $shipment) => $shipment->deliveryNotes->flatMap->orders)
            ->unique('id')
            ->filter(fn (Order $order) => ltrim(preg_replace('/\D/', '', $order->reference), '0') === ltrim($reference, '0'));

        return $orders->count() === 1 ? $orders->first() : null;
    }

    /**
     * @return Collection<int, Payment>
     */
    private function codPayments(Order $order): Collection
    {
        return $order->payments()
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
            ->whereHas('paymentAccount', fn ($query) => $query->where('type', PaymentAccountTypeEnum::CASH_ON_DELIVERY))
            ->get();
    }

    /**
     * A shop can have more than one active cash on delivery account in the order's currency (HU has the one staff
     * record on and the one the checkout offers): the one this shop recorded most cash on delivery payments on lately.
     */
    private function codAccount(Order $order): ?PaymentAccount
    {
        $paymentAccountIds = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::CASH_ON_DELIVERY)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('currency_id', $order->currency_id)
            ->pluck('payment_account_id');
        if ($paymentAccountIds->count() <= 1) {
            return PaymentAccount::find($paymentAccountIds->first());
        }

        $mostUsed = Payment::whereIn('payment_account_id', $paymentAccountIds)
            ->where('shop_id', $order->shop_id)
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('payment_account_id')
            ->orderByRaw('count(*) desc')
            ->value('payment_account_id');

        return PaymentAccount::find($mostUsed ?? $paymentAccountIds->min());
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $commit = (bool)$command->option('commit');
        foreach ($this->handle($commit, (int)$command->option('days')) as $outcome => $count) {
            $command->info("$outcome: $count");
        }
        foreach ($this->samples as $sample) {
            $command->line(json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        if (!$commit) {
            $command->warn('Dry run: nothing written, run again with --commit');
        }

        return 0;
    }
}

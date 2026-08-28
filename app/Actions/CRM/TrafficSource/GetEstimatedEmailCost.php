<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Helpers\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEstimatedEmailCost
{
    use AsAction;

    /**
     * The automated emails cost the same per message as any other: SES bills for a send, not for the
     * reason behind it. They are not mailshots, so they are counted from the dispatched emails of the
     * outboxes we treat as marketing.
     *
     * `$costPerEmail` is accepted so a screen pricing several channels resolves the rate once rather
     * than looking the currency up again for every one of them.
     *
     * @param array<int, int>|\Illuminate\Support\Collection<int, int> $shopIds
     */
    public static function automated($shopIds, ?Carbon $from, ?Carbon $to, Currency $currency, ?float $costPerEmail = null): float
    {
        $codes = (array) config('marketing.attributed_outbox_codes', []);

        if ($codes === []) {
            return 0.0;
        }

        $dispatched = DB::table('dispatched_emails as de')
            ->join('outboxes as o', 'o.id', '=', 'de.outbox_id')
            ->whereIn('o.shop_id', $shopIds)
            ->whereIn('o.code', $codes)
            /* On sent_at, not created_at: SES bills for a send, so a row that was created and never
               sent cost us nothing and must not be charged. It is also the fast column - counting on
               the unindexed created_at seq-scanned all 320 million rows and cost the dashboard 18
               seconds a load. */
            ->when($from, fn ($query) => $query->where('de.sent_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('de.sent_at', '<=', $to))
            ->count();

        if (!$dispatched) {
            return 0.0;
        }

        return round($dispatched * ($costPerEmail ?? self::make()->costPerEmail($currency)), 2);
    }

    /**
     * Both figures a period's mailshots owe the dashboard - what they cost to send, and the
     * subscribers they lost - in one pass over the table, bucketed by mailshot type. The filter is an
     * expression over `sent_at`/`created_at` and cannot use an index, so a screen reporting several
     * email channels reads the table once here instead of once per channel per figure.
     *
     * @param array<int, int>|Collection<int, int> $shopIds
     *
     * @return Collection<string, array{dispatched: int, unsubscribed: int}>
     */
    public static function totalsByType($shopIds, ?Carbon $from, ?Carbon $to, ?array $types = null): Collection
    {
        return Mailshot::whereIn('mailshots.shop_id', $shopIds)
            ->whereIn('mailshots.type', $types ?? self::allTypes())
            ->when($from, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) >= ?', [$from]))
            ->when($to, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) <= ?', [$to]))
            ->join('mailshot_stats', 'mailshot_stats.mailshot_id', '=', 'mailshots.id')
            ->groupBy('mailshots.type')
            ->select(
                'mailshots.type',
                DB::raw('SUM(mailshot_stats.number_dispatched_emails) as dispatched'),
                DB::raw('SUM(mailshot_stats.number_dispatched_emails_state_unsubscribed) as unsubscribed'),
            )
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->type instanceof MailshotTypeEnum ? $row->type->value : (string) $row->type) => [
                    'dispatched'   => (int) $row->dispatched,
                    'unsubscribed' => (int) $row->unsubscribed,
                ],
            ]);
    }

    /**
     * One email channel's share of a `totalsByType()` result: a channel is more than one mailshot
     * type, and the dashboard reports them apart.
     *
     * @param Collection<string, array{dispatched: int, unsubscribed: int}> $totalsByType
     *
     * @return array{cost: float, unsubscribed: int}
     */
    public static function forChannel(Collection $totalsByType, TrafficSourcesTypeEnum $channel, float $costPerEmail): array
    {
        $dispatched   = 0;
        $unsubscribed = 0;

        foreach (self::typesFor($channel) as $type) {
            $dispatched   += $totalsByType[$type->value]['dispatched'] ?? 0;
            $unsubscribed += $totalsByType[$type->value]['unsubscribed'] ?? 0;
        }

        return [
            'cost'         => $dispatched ? round($dispatched * $costPerEmail, 2) : 0.0,
            'unsubscribed' => $unsubscribed,
        ];
    }

    /** Mailshot types split the way the channels do: a newsletter is one instrument, a promotional
     *  mailshot another.
     *
     * @return array<int, MailshotTypeEnum>
     */
    public static function typesFor(TrafficSourcesTypeEnum $channel): array
    {
        return $channel === TrafficSourcesTypeEnum::NEWSLETTER
            ? [MailshotTypeEnum::NEWSLETTER]
            : [MailshotTypeEnum::MARKETING, MailshotTypeEnum::INVITE];
    }

    /** @return array<int, MailshotTypeEnum> */
    private static function allTypes(): array
    {
        return [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING, MailshotTypeEnum::INVITE];
    }

    /**
     * Subscribers lost over the same emails, which is the newsletter's other cost. Money is not the
     * only thing a mailshot spends: a send that earns well while burning forty subscribers has taken
     * something that cannot be bought back, and nothing on the dashboard said so.
     *
     * @param array<int, int>|\Illuminate\Support\Collection<int, int> $shopIds
     */
    public static function unsubscribes($shopIds, ?Carbon $from, ?Carbon $to, ?array $types = null): int
    {
        return (int) Mailshot::whereIn('mailshots.shop_id', $shopIds)
            ->whereIn('type', $types ?? self::allTypes())
            ->when($from, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) >= ?', [$from]))
            ->when($to, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) <= ?', [$to]))
            ->join('mailshot_stats', 'mailshot_stats.mailshot_id', '=', 'mailshots.id')
            ->sum('mailshot_stats.number_dispatched_emails_state_unsubscribed');
    }

    /**
     * What the newsletter channel actually cost to run in a period.
     *
     * Sending is not free, but there is no cost row for it: nobody invoices us per mailshot, so the
     * channel showed a spend of zero and an infinite return. This prices the emails that were
     * dispatched at the SES per-message rate, which is what the email panel already reports per
     * mailshot - the same figure, for the whole period rather than the most recent few.
     *
     * An estimate, and labelled as one wherever it is shown.
     *
     * @param array<int, int>|\Illuminate\Support\Collection<int, int> $shopIds
     */
    public function handle($shopIds, ?Carbon $from, ?Carbon $to, Currency $currency, ?array $types = null): float
    {
        $dispatched = Mailshot::whereIn('mailshots.shop_id', $shopIds)
            ->whereIn('type', $types ?? self::allTypes())
            ->when($from, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) >= ?', [$from]))
            ->when($to, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) <= ?', [$to]))
            ->join('mailshot_stats', 'mailshot_stats.mailshot_id', '=', 'mailshots.id')
            ->sum('mailshot_stats.number_dispatched_emails');

        if (!$dispatched) {
            return 0.0;
        }

        return round($dispatched * $this->costPerEmail($currency), 2);
    }

    /**
     * Falls back to 1:1 when no rate is available; for a cost this small a stale-or-missing rate must
     * never take the dashboard down, and the figure is labelled an estimate anyway.
     */
    public function costPerEmail(Currency $currency): float
    {
        $usd  = Currency::where('code', 'USD')->first();
        $rate = (!$usd || $usd->id === $currency->id)
            ? 1.0
            : (GetCurrencyExchange::run($usd, $currency) ?? 1.0);

        return ((float) config('services.ses.cost_per_thousand_usd')) / 1000 * $rate;
    }
}

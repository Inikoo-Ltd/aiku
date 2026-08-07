<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Helpers\Currency;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEstimatedEmailCost
{
    use AsAction;

    /**
     * Subscribers lost over the same emails, which is the newsletter's other cost. Money is not the
     * only thing a mailshot spends: a send that earns well while burning forty subscribers has taken
     * something that cannot be bought back, and nothing on the dashboard said so.
     *
     * @param array<int, int>|\Illuminate\Support\Collection<int, int> $shopIds
     */
    public static function unsubscribes($shopIds, ?Carbon $from): int
    {
        return (int) Mailshot::whereIn('mailshots.shop_id', $shopIds)
            ->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING, MailshotTypeEnum::INVITE])
            ->when($from, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) >= ?', [$from]))
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
    public function handle($shopIds, ?Carbon $from, Currency $currency): float
    {
        $dispatched = Mailshot::whereIn('mailshots.shop_id', $shopIds)
            ->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING, MailshotTypeEnum::INVITE])
            ->when($from, fn ($query) => $query->whereRaw('COALESCE(mailshots.sent_at, mailshots.created_at) >= ?', [$from]))
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
    private function costPerEmail(Currency $currency): float
    {
        $usd  = Currency::where('code', 'USD')->first();
        $rate = (!$usd || $usd->id === $currency->id)
            ? 1.0
            : (GetCurrencyExchange::run($usd, $currency) ?? 1.0);

        return ((float) config('services.ses.cost_per_thousand_usd')) / 1000 * $rate;
    }
}

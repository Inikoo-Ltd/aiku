<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotStats;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Carbon;
use App\Models\Helpers\Currency;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopEmailMarketingPerformance
{
    use AsAction;
    use WithAttributionWindow;

    /**
     * Answers, per mailshot and in total, whether the emails a shop sends earn sales or just annoy
     * people: delivery/open/click/unsubscribe engagement from the mailshot stats, an estimated send
     * cost from the SES per-message price, and the share-weighted revenue and customers attributed to
     * each mailshot's click touchpoints. For prospect mailshots the conversion that matters is not a
     * sale but a registration, so each row also counts prospects who clicked and later became
     * customers.
     *
     * Totals cover every mailshot sent in the period; only the per-mailshot rows stop at the limit.
     *
     * Attribution shares mean a mailshot never claims the whole of a sale a paid ad also touched;
     * summed across every channel the revenue here adds up to the shop's real revenue, not a multiple
     * of it.
     *
     * @return array{totals: array{sent: int, delivered: int, opened: int, clicked: int, bounced: int, spam: int, unsubscribed: int, estimated_cost: float, attributed_revenue: float, attributed_customers: float}, mailshots: array<int, array{id: int, subject: string, type: string, sent_at: string|null, sent: int, opened: int, clicked: int, unsubscribed: int, estimated_cost: float, attributed_revenue: float, attributed_customers: float, prospects_registered: int}>}
     */
    public function handle(Shop $shop, ?Carbon $from = null, ?Carbon $to = null, int $limit = 8): array
    {
        $usdToShop = $this->usdToShopRate($shop);
        $costPerEmail = ((float) config('services.ses.cost_per_thousand_usd')) / 1000 * $usdToShop;

        /* Filtered by when the mailshot was sent, not when its clicks earned revenue: the question
           this panel answers is whether the emails sent in a period paid for themselves. */
        $periodMailshots = Mailshot::where('shop_id', $shop->id)
            ->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING, MailshotTypeEnum::INVITE])
            ->whereHas('stats', fn ($query) => $query->where('number_dispatched_emails', '>', 0))
            ->when($from, fn ($query) => $query->whereRaw('COALESCE(sent_at, created_at) >= ?', [$from]))
            ->when($to, fn ($query) => $query->whereRaw('COALESCE(sent_at, created_at) <= ?', [$to]));

        $mailshots = (clone $periodMailshots)
            ->with('stats')
            ->orderByRaw('COALESCE(sent_at, created_at) DESC, id DESC')
            ->limit($limit)
            ->get();

        /* Opens and clicks count recipients, not events: someone who opened five times or clicked
           three links counts once, the way mail platforms report open and click rates. */
        $engagement = MailshotStats::whereIn('mailshot_id', (clone $periodMailshots)->select('id'))
            ->selectRaw('
                COALESCE(SUM(number_dispatched_emails), 0) as sent,
                COALESCE(SUM(number_deliveries_success), 0) as delivered,
                COALESCE(SUM(number_delivered_open_success), 0) as opened,
                COALESCE(SUM(number_opened_interact_success), 0) as clicked,
                COALESCE(SUM(number_dispatched_emails_state_hard_bounce + number_dispatched_emails_state_soft_bounce), 0) as bounced,
                COALESCE(SUM(number_dispatched_emails_state_spam), 0) as spam,
                COALESCE(SUM(number_dispatched_emails_state_unsubscribed), 0) as unsubscribed
            ')
            ->first();

        $campaignByMailshot = TrafficSourceCampaign::query()
            /* Both namespaces: a newsletter's campaign is mailshot-N, a marketing mailshot's is
               mmailshot-N, because `reference` is unique across the whole table. */
            ->whereIn('reference', (clone $periodMailshots)->pluck('id')->flatMap(fn (int $mailshotId) => [
                RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshotId,
                RecordEmailClickTouchpoint::MARKETING_CAMPAIGN_REF_PREFIX.$mailshotId,
            ]))
            ->pluck('id', 'reference');

        $campaignIds = $campaignByMailshot->values();

        $attributionWindow = GetAttributionWindow::run($shop);

        $customerTotals = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($attributionWindow) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $attributionWindow);
            })
            ->whereIn('p.traffic_source_campaign_id', $campaignIds)
            ->where('invoices.in_process', false)
            ->groupBy('p.traffic_source_campaign_id')
            ->select(
                'p.traffic_source_campaign_id as campaign_id',
                DB::raw('SUM(p.share) as customers'),
                DB::raw('SUM(invoices.net_amount * p.share) as revenue'),
            )
            ->get()
            ->keyBy('campaign_id');

        $prospectConversions = DB::table('model_has_traffic_sources')
            ->join('prospects', 'prospects.id', '=', 'model_has_traffic_sources.model_id')
            ->where('model_has_traffic_sources.model_type', 'Prospect')
            ->whereIn('model_has_traffic_sources.traffic_source_campaign_id', $campaignIds)
            ->whereNotNull('prospects.customer_id')
            ->groupBy('model_has_traffic_sources.traffic_source_campaign_id')
            ->select(
                'model_has_traffic_sources.traffic_source_campaign_id as campaign_id',
                DB::raw('COUNT(*) as registered'),
            )
            ->get()
            ->keyBy('campaign_id');

        $rows = $mailshots->map(function (Mailshot $mailshot) use ($campaignByMailshot, $customerTotals, $prospectConversions, $costPerEmail) {
            $campaignId = $campaignByMailshot->get(RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id)
                ?? $campaignByMailshot->get(RecordEmailClickTouchpoint::MARKETING_CAMPAIGN_REF_PREFIX.$mailshot->id);
            $attribution = $campaignId ? $customerTotals->get($campaignId) : null;

            return [
                'id'                   => $mailshot->id,
                'subject'              => $mailshot->subject,
                'type'                 => $mailshot->type->value,
                'sent_at'              => $mailshot->sent_at?->toDateString(),
                'sent'                 => (int) $mailshot->stats->number_dispatched_emails,
                'opened'               => (int) $mailshot->stats->number_dispatched_emails_state_opened
                                        + (int) $mailshot->stats->number_dispatched_emails_state_clicked,
                'clicked'              => (int) $mailshot->stats->number_dispatched_emails_state_clicked,
                'unsubscribed'         => (int) $mailshot->stats->number_dispatched_emails_state_unsubscribed,
                'estimated_cost'       => round($mailshot->stats->number_dispatched_emails * $costPerEmail, 2),
                'attributed_revenue'   => round((float) ($attribution->revenue ?? 0), 2),
                'attributed_customers' => round((float) ($attribution->customers ?? 0), 2),
                'prospects_registered' => (int) ($campaignId ? ($prospectConversions->get($campaignId)->registered ?? 0) : 0),
            ];
        })->all();

        return [
            'totals'    => [
                'sent'                 => (int) $engagement->sent,
                'delivered'            => (int) $engagement->delivered,
                'opened'               => (int) $engagement->opened,
                'clicked'              => (int) $engagement->clicked,
                'bounced'              => (int) $engagement->bounced,
                'spam'                 => (int) $engagement->spam,
                'unsubscribed'         => (int) $engagement->unsubscribed,
                'estimated_cost'       => round($engagement->sent * $costPerEmail, 2),
                'attributed_revenue'   => round((float) $customerTotals->sum('revenue'), 2),
                'attributed_customers' => round((float) $customerTotals->sum('customers'), 2),
            ],
            'mailshots' => $rows,
        ];
    }

    /**
     * Falls back to 1:1 when no rate is available; for a cost this small a stale-or-missing rate must
     * never take the dashboard down, and the figure is labelled an estimate.
     */
    private function usdToShopRate(Shop $shop): float
    {
        $usd = Currency::where('code', 'USD')->first();

        if (!$usd || $usd->id === $shop->currency_id) {
            return 1.0;
        }

        return GetCurrencyExchange::run($usd, $shop->currency) ?? 1.0;
    }
}

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
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Helpers\Currency;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopEmailMarketingPerformance
{
    use AsAction;

    /**
     * Answers, per mailshot and in total, whether the emails a shop sends earn sales or just annoy
     * people: delivery/open/click/unsubscribe engagement from the mailshot stats, an estimated send
     * cost from the SES per-message price, and the share-weighted revenue and customers attributed to
     * each mailshot's click touchpoints. For prospect mailshots the conversion that matters is not a
     * sale but a registration, so each row also counts prospects who clicked and later became
     * customers.
     *
     * Attribution shares mean a mailshot never claims the whole of a sale a paid ad also touched;
     * summed across every channel the revenue here adds up to the shop's real revenue, not a multiple
     * of it.
     *
     * @return array{totals: array{sent: int, opened: int, clicked: int, unsubscribed: int, estimated_cost: float, attributed_revenue: float, attributed_customers: float}, mailshots: array<int, array{id: int, subject: string, type: string, sent_at: string|null, sent: int, opened: int, clicked: int, unsubscribed: int, estimated_cost: float, attributed_revenue: float, attributed_customers: float, prospects_registered: int}>}
     */
    public function handle(Shop $shop, int $limit = 8): array
    {
        $usdToShop = $this->usdToShopRate($shop);
        $costPerEmail = ((float) config('services.ses.cost_per_thousand_usd')) / 1000 * $usdToShop;

        $mailshots = Mailshot::where('shop_id', $shop->id)
            ->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING, MailshotTypeEnum::INVITE])
            ->whereHas('stats', fn ($query) => $query->where('number_dispatched_emails', '>', 0))
            ->with('stats')
            ->orderByRaw('COALESCE(sent_at, created_at) DESC, id DESC')
            ->limit($limit)
            ->get();

        $campaignByMailshot = TrafficSourceCampaign::query()
            ->whereIn('reference', $mailshots->map(
                fn (Mailshot $mailshot) => RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id
            ))
            ->pluck('id', 'reference');

        $campaignIds = $campaignByMailshot->values();

        $customerTotals = DB::table('model_has_traffic_sources')
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'model_has_traffic_sources.model_id')
            ->where('model_has_traffic_sources.model_type', 'Customer')
            ->whereIn('model_has_traffic_sources.traffic_source_campaign_id', $campaignIds)
            ->groupBy('model_has_traffic_sources.traffic_source_campaign_id')
            ->select(
                'model_has_traffic_sources.traffic_source_campaign_id as campaign_id',
                DB::raw('SUM(model_has_traffic_sources.share) as customers'),
                DB::raw('SUM(customer_stats.sales_all * model_has_traffic_sources.share) as revenue'),
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
            $campaignId = $campaignByMailshot->get(RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id);
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
                'sent'                 => array_sum(array_column($rows, 'sent')),
                'opened'               => array_sum(array_column($rows, 'opened')),
                'clicked'              => array_sum(array_column($rows, 'clicked')),
                'unsubscribed'         => array_sum(array_column($rows, 'unsubscribed')),
                'estimated_cost'       => round(array_sum(array_column($rows, 'estimated_cost')), 2),
                'attributed_revenue'   => round(array_sum(array_column($rows, 'attributed_revenue')), 2),
                'attributed_customers' => round(array_sum(array_column($rows, 'attributed_customers')), 2),
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

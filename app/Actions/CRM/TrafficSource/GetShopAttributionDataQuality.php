<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopAttributionDataQuality
{
    use AsAction;

    public const STATUS_OK      = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR   = 'error';

    /**
     * Attribution fails quietly: a broken capture script, an unseeded channel or a campaign reference
     * nothing matches all show up as smaller numbers rather than as errors. These checks make each of
     * those failures visible on its own, per shop.
     *
     * @return array{period: string, period_label: string, from: string|null, capture: array{hits: int, identified_pct: float|null, rows: array<int, array{visitor: string, hits: int, matched: int, repeat: int, direct: int, unmatched: int, identified: string}>, rejected: array<int, array{host: string, hits: int}>}, checks: array<int, array{key: string, label: string, status: string, value: string, hint: string, items: array<int, string>}>}
     */
    public function handle(Shop $shop, MarketingPeriodEnum $period = MarketingPeriodEnum::LAST_30): array
    {
        $from = $period->startsAt();

        return [
            'period'       => $period->value,
            'period_label' => MarketingPeriodEnum::labels()[$period->value],
            'from'         => $from?->toDateString(),
            'checks'       => [
                $this->registrationsWithoutAttribution($shop, $from),
                $this->sharesNotSummingToOne($shop),
                $this->missingTrafficSources($shop),
                $this->neverCreditedTrafficSources($shop),
                $this->unmatchedCampaignReferences($shop, $from),
            ],
            'capture' => $this->captureToday(),
        ];
    }

    /**
     * Today's capture counters, written by CaptureTrafficSource on every storefront first hit. Not
     * shop-scoped - the counters are estate-wide - but this is the only screen where anyone looks at
     * attribution health, and a command nobody runs answers nothing.
     *
     * @return array{hits: int, identified_pct: float|null, rows: array<int, array{visitor: string, hits: int, matched: int, repeat: int, direct: int, unmatched: int, identified: string}>, rejected: array<int, array{host: string, hits: int}>}
     */
    private function captureToday(): array
    {
        $day      = now()->toDateString();
        $outcomes = ['matched', 'repeat', 'direct', 'unmatched'];
        $rows     = [];
        $allHits  = 0;
        $allKnown = 0;

        foreach (['anon' => __('Anonymous'), 'auth' => __('Logged in')] as $audience => $label) {
            $counts = [];

            foreach ($outcomes as $outcome) {
                $counts[$outcome] = (int) Cache::get('traffic_capture:'.$day.':'.$audience.':'.$outcome, 0);
            }

            $hits       = array_sum($counts);
            $identified = $counts['matched'] + $counts['repeat'];
            $allHits   += $hits;
            $allKnown  += $identified;

            $rows[] = array_merge(['visitor' => $label, 'hits' => $hits], $counts, [
                'identified' => $hits > 0 ? round($identified / $hits * 100, 1).'%' : '-',
            ]);
        }

        $rejected = collect(Cache::get('traffic_capture:'.$day.':hosts', []))
            ->sortDesc()
            ->take(15)
            ->map(fn (int $hits, string $host) => ['host' => $host, 'hits' => $hits])
            ->values()
            ->all();

        return [
            'hits'           => $allHits,
            'identified_pct' => $allHits > 0 ? round($allKnown / $allHits * 100, 1) : null,
            'rows'           => $rows,
            'rejected'       => $rejected,
        ];
    }

    /**
     * The single best signal that capture has broken: customers who registered without a single touch
     * recorded. A shop always has some, since direct and untracked arrivals are real, so this reads as
     * a proportion rather than a count, and only a high one is an alarm.
     */
    private function registrationsWithoutAttribution(Shop $shop, ?Carbon $from): array
    {
        $registrations = DB::table('customers')
            ->where('shop_id', $shop->id)
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->count();

        $unattributed = DB::table('customers')
            ->where('shop_id', $shop->id)
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('model_has_traffic_sources as p')
                ->whereColumn('p.model_id', 'customers.id')
                ->where('p.model_type', 'Customer'))
            ->count();

        $percentage = $registrations > 0 ? round($unattributed / $registrations * 100, 1) : 0.0;

        return [
            'key'    => 'registrations_without_attribution',
            'label'  => __('Registrations with no attribution'),
            'status' => match (true) {
                $registrations === 0 => self::STATUS_OK,
                $percentage >= 90    => self::STATUS_ERROR,
                $percentage >= 60    => self::STATUS_WARNING,
                default              => self::STATUS_OK,
            },
            'value'  => $registrations > 0
                ? $unattributed.' / '.$registrations.' ('.$percentage.'%)'
                : __('No registrations in this period'),
            'hint'   => __('Some untracked arrivals are normal. A sudden jump means the capture script or the touch cookie has stopped working.'),
            'items'  => [],
        ];
    }

    /**
     * Every customer's shares must total exactly 1.00; anything else means attribution was written
     * twice, or partially. This is the invariant, so a single offender is already an error.
     */
    private function sharesNotSummingToOne(Shop $shop): array
    {
        $offenders = DB::table('model_has_traffic_sources as p')
            ->join('customers', 'customers.id', '=', 'p.model_id')
            ->where('p.model_type', 'Customer')
            ->where('customers.shop_id', $shop->id)
            ->groupBy('customers.id', 'customers.name')
            ->havingRaw('round(sum(p.share)::numeric, 2) <> 1.00')
            ->select('customers.id', 'customers.name', DB::raw('round(sum(p.share)::numeric, 2) as total'))
            /* ponytail: the invariant says this is empty, so the query is capped rather than counted
               in full; if it ever is not empty the first offenders are enough to find the cause. */
            ->limit(21)
            ->get();

        return [
            'key'    => 'shares_not_summing_to_one',
            'label'  => __('Customers whose shares do not sum to 1.00'),
            'status' => $offenders->isEmpty() ? self::STATUS_OK : self::STATUS_ERROR,
            'value'  => match (true) {
                $offenders->isEmpty()    => __('None'),
                $offenders->count() > 20 => '20+',
                default                  => (string) $offenders->count(),
            },
            'hint'   => __('Should always be zero. Rebuild the affected customers with traffic-source:recalculate-attribution.'),
            'items'  => $offenders->take(20)->map(fn ($row) => '#'.$row->id.' '.$row->name.' = '.$row->total)->all(),
        ];
    }

    /**
     * A channel with no row in this shop discards every touch that names it, silently: newsletter went
     * missing this way and a day of mailshot clicks was lost before anyone noticed.
     */
    private function missingTrafficSources(Shop $shop): array
    {
        $existing = DB::table('traffic_sources')->where('shop_id', $shop->id)->pluck('type')->all();
        $missing  = array_diff(TrafficSourcesTypeEnum::values(), $existing);

        return [
            'key'    => 'missing_traffic_sources',
            'label'  => __('Channels with no row in this shop'),
            'status' => empty($missing) ? self::STATUS_OK : self::STATUS_ERROR,
            'value'  => empty($missing) ? __('None') : (string) count($missing),
            'hint'   => __('Touches naming these channels are discarded. Run traffic-source:seed.'),
            'items'  => array_values(array_map(fn (string $type) => TrafficSourcesTypeEnum::labels()[$type], $missing)),
        ];
    }

    /**
     * A channel that exists but has never been credited a single customer. Expected for channels the
     * shop does not use; suspicious for one it does.
     */
    private function neverCreditedTrafficSources(Shop $shop): array
    {
        $uncredited = DB::table('traffic_sources')
            ->where('shop_id', $shop->id)
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('model_has_traffic_sources as p')
                ->whereColumn('p.traffic_source_id', 'traffic_sources.id')
                ->where('p.model_type', 'Customer'))
            ->orderBy('name')
            ->pluck('name');

        return [
            'key'    => 'never_credited_traffic_sources',
            'label'  => __('Channels never credited a customer'),
            'status' => $uncredited->isEmpty() ? self::STATUS_OK : self::STATUS_WARNING,
            'value'  => $uncredited->isEmpty() ? __('None') : (string) $uncredited->count(),
            'hint'   => __('Normal for channels this shop does not use. Worth checking for one it does.'),
            'items'  => $uncredited->all(),
        ];
    }

    /**
     * Campaign references carried by touches that matched no campaign row. Those touches still credit
     * their channel, but drop out of every per-campaign figure, so spend imported against the same
     * reference has nothing to compare itself with.
     */
    private function unmatchedCampaignReferences(Shop $shop, ?Carbon $from): array
    {
        /* ponytail: campaign refs live inside the touch string, so they can only be found by parsing
           it. All time would mean parsing every customer the shop ever had on a page load, so the scan
           is bounded to 90 days when no period start is given. Move it to a scheduled job if a longer
           reach is ever needed. */
        $scanFrom  = $from ?? now()->subDays(90);
        $sources   = DB::table('traffic_sources')->where('shop_id', $shop->id)->pluck('id', 'type');
        $campaigns = DB::table('traffic_source_campaigns')
            ->whereIn('traffic_source_id', $sources->values())
            ->get()
            ->map(fn ($campaign) => $campaign->traffic_source_id.'|'.$campaign->reference)
            ->flip();

        $unmatched = [];

        DB::table('customers')
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', $scanFrom)
            ->whereNotNull('traffic_sources')
            ->where('traffic_sources', '!=', '')
            ->select('id', 'traffic_sources')
            ->orderBy('id')
            ->chunk(1000, function ($customers) use (&$unmatched, $sources, $campaigns) {
                foreach ($customers as $customer) {
                    foreach (ParseTrafficSourceTouches::run($customer->traffic_sources) as $touch) {
                        if ($touch['campaign_ref'] === null) {
                            continue;
                        }

                        $sourceId = $sources[$touch['type']->value] ?? null;

                        if ($sourceId === null || $campaigns->has($sourceId.'|'.$touch['campaign_ref'])) {
                            continue;
                        }

                        $key             = TrafficSourcesTypeEnum::labels()[$touch['type']->value].': '.$touch['campaign_ref'];
                        $unmatched[$key] = ($unmatched[$key] ?? 0) + 1;
                    }
                }
            });

        arsort($unmatched);
        $top = array_slice($unmatched, 0, 20, true);

        return [
            'key'    => 'unmatched_campaign_references',
            'label'  => __('Campaign references matching no campaign'),
            'status' => empty($unmatched) ? self::STATUS_OK : self::STATUS_WARNING,
            'value'  => empty($unmatched) ? __('None') : (string) count($unmatched),
            'hint'   => __('These touches credit their channel but no campaign. Non numeric references are never auto created, so they need a campaign row before they can be reported on.'),
            'items'  => array_map(
                fn (string $key, int $count) => $key.' ('.$count.' '.__('touches').')',
                array_keys($top),
                array_values($top)
            ),
        ];
    }
}

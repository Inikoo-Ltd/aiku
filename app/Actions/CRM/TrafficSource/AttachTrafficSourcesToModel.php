<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceCampaignHydrateStats;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class AttachTrafficSourcesToModel
{
    use AsAction;

    /* ponytail: a flat cap, enough for every real referrer a shop will ever have. Swap for a
       rate-limited allow-list only if junk rows actually show up. */
    private const MAX_REFERRAL_CAMPAIGNS = 200;

    /**
     * Resolves the parsed marketing touches into shop traffic sources and campaigns and writes the
     * calculated attribution shares onto the model's `trafficSources` pivot, one row per
     * (source, campaign) pair, so a customer who engaged with two campaigns of the same source keeps
     * both credited separately and per-campaign reporting sees them. Shares across all rows of a
     * model still sum to 1.0.
     *
     * A campaign reference seen in a touch with no matching campaign row creates one, so the campaign
     * ids that ad platforms put in landing URLs become reportable entities the moment the first
     * visitor arrives with one, and cost imports have something to match against.
     *
     * Every caller runs against a model with no pivot rows for these touches (fresh registration,
     * order submit, or a recalculation that detached first), so rows are inserted, never merged.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, type: \App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     */
    public function handle(Model $model, int $shopId, array $touches, string $attributionModel = ProcessTrafficSourceShare::ATTRIBUTION_LINEAR): void
    {
        if (empty($touches)) {
            return;
        }

        /* Every filter runs before the shares are calculated, never after. Dropping a credited touch
           later leaves the rest summing to under 1.00, which is exactly how a customer ended up with
           half a registration when a referral touch had no channel row to attach to. */
        $touches = $this->withoutUnusableReferralTouches($touches);

        /** @var Collection<string, TrafficSource> $trafficSources */
        $trafficSources = TrafficSource::where('shop_id', $shopId)
            ->whereIn('type', collect($touches)->map(fn (array $touch) => $touch['type']->value)->unique()->all())
            ->get()
            ->keyBy('type');

        /* A channel the shop has no row for cannot be credited, so its touch is removed and the
           remaining touches share the whole of the credit between them. Seeding a new enum case is
           still mandatory - this only stops the gap corrupting everyone else's shares meanwhile. */
        $touches = array_values(array_filter(
            $touches,
            fn (array $touch) => $trafficSources->has($touch['type']->value)
        ));

        if (empty($touches)) {
            return;
        }

        $shares = ProcessTrafficSourceShare::run($touches, $attributionModel);

        if (empty($shares)) {
            return;
        }

        $touchedSourceIds   = [];
        $touchedCampaignIds = [];
        $touchDates         = $this->touchDates($touches);

        foreach ($shares as $share) {
            /** @var TrafficSource|null $trafficSource */
            $trafficSource = $trafficSources->get($share['type']->value);

            if (!$trafficSource) {
                continue;
            }

            $campaignId = $share['campaign_ref']
                ? $this->resolveCampaignId($trafficSource, $share['campaign_ref'])
                : null;

            $dates = $touchDates[$share['type']->value.'|'.($share['campaign_ref'] ?? '')] ?? null;

            $model->trafficSources()->attach($trafficSource->id, [
                'share'                      => round($share['share'], 2),
                'traffic_source_campaign_id' => $campaignId,
                'attribution_model'          => $attributionModel,
                'first_touch_at'             => $dates['first'] ?? null,
                'last_touch_at'              => $dates['last'] ?? null,
            ]);

            $touchedSourceIds[$trafficSource->id] = true;

            if ($campaignId) {
                $touchedCampaignIds[$campaignId] = true;
            }
        }

        foreach ($trafficSources->whereIn('id', array_keys($touchedSourceIds)) as $trafficSource) {
            TrafficSourceHydrateCustomers::dispatch($trafficSource);
        }

        foreach (TrafficSourceCampaign::whereIn('id', array_keys($touchedCampaignIds))->get() as $campaign) {
            TrafficSourceCampaignHydrateStats::dispatch($campaign);
        }
    }

    /**
     * A referral touch is only meaningful if its host survives validation: malformed hosts, our own
     * admin and our own storefronts are not acquisitions. Stored touch strings predate this check, so
     * it runs on the way in as well as at capture.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}>
     */
    private function withoutUnusableReferralTouches(array $touches): array
    {
        return array_values(array_filter(
            $touches,
            fn (array $touch) => !in_array($touch['type'], [
                TrafficSourcesTypeEnum::REFERRAL,
                TrafficSourcesTypeEnum::ORGANIC_SEARCH,
            ], true)
                || GetTrafficSourceFromRefererHeader::normaliseHost($touch['campaign_ref']) === $touch['campaign_ref']
        ));
    }

    /**
     * When each source-and-campaign combination was first and last seen. Revenue attribution needs
     * this: without it a touch recorded today can be credited with purchases made years earlier.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}> $touches
     *
     * @return array<string, array{first: Carbon|null, last: Carbon|null}>
     */
    private function touchDates(array $touches): array
    {
        $dates = [];

        foreach ($touches as $touch) {
            if ($touch['timestamp'] === null) {
                continue;
            }

            $key  = $touch['type']->value.'|'.($touch['campaign_ref'] ?? '');
            $when = Carbon::createFromTimestamp($touch['timestamp']);

            if (!isset($dates[$key])) {
                $dates[$key] = ['first' => $when, 'last' => $when];

                continue;
            }

            if ($when->lt($dates[$key]['first'])) {
                $dates[$key]['first'] = $when;
            }

            if ($when->gt($dates[$key]['last'])) {
                $dates[$key]['last'] = $when;
            }
        }

        return $dates;
    }

    private function resolveCampaignId(TrafficSource $trafficSource, string $reference): ?int
    {
        try {
            $campaignId = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)
                ->where('reference', $reference)
                ->value('id');

            if ($campaignId) {
                return $campaignId;
            }

            /* Touch strings originate in client-controlled cookies, so auto-creation is limited to
               references shaped like the numeric campaign ids ad platforms actually issue. Anything
               else (including the mailshot-N refs, which the click pipeline creates server-side with
               a proper name) must already exist to be credited; otherwise a visitor cycling made-up
               references could mint unlimited campaign rows named whatever they like. */
            /* Referral campaigns are the referring host itself, so they cannot be numeric. They are
               still client-controlled, hence the hostname shape check and the per-source cap: worst
               case a spoofed Referer buys a bounded number of junk rows, not unlimited ones. */
            if (in_array($trafficSource->type, [
                TrafficSourcesTypeEnum::REFERRAL->value,
                TrafficSourcesTypeEnum::ORGANIC_SEARCH->value,
            ], true)) {
                if (GetTrafficSourceFromRefererHeader::normaliseHost($reference) !== $reference) {
                    return null;
                }

                if (TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)->count() >= self::MAX_REFERRAL_CAMPAIGNS) {
                    return null;
                }
            } elseif (!preg_match('/^(?:'.preg_quote(TrafficSourcesTypeEnum::INSTAGRAM_CAMPAIGN_PREFIX, '/').')?\d{1,20}$/', $reference)) {
                /* Instagram campaign references carry a prefix so they cannot collide with the Facebook
                   half of the same Meta campaign; the id after it is still the platform's own. */
                return null;
            }

            return TrafficSourceCampaign::firstOrCreate(
                [
                    'traffic_source_id' => $trafficSource->id,
                    'reference'         => $reference,
                ],
                [
                    'name' => $reference,
                    'type' => $trafficSource->type,
                ]
            )->id;
        } catch (\Throwable) {
            /* `reference` is globally unique: the same ad-platform campaign id appearing under a
               different shop's source cannot create a second row. The touch keeps its source-level
               share; only the campaign breakdown is unavailable for it. */
            return null;
        }
    }
}

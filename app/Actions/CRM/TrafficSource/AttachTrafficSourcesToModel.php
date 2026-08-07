<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceCampaignHydrateStats;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class AttachTrafficSourcesToModel
{
    use AsAction;

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

        $shares = ProcessTrafficSourceShare::run($touches, $attributionModel);

        if (empty($shares)) {
            return;
        }

        $typeValues = collect($shares)->map(fn (array $share) => $share['type']->value)->unique()->all();

        /** @var Collection<string, TrafficSource> $trafficSources */
        $trafficSources = TrafficSource::where('shop_id', $shopId)
            ->whereIn('type', $typeValues)
            ->get()
            ->keyBy('type');

        if ($trafficSources->isEmpty()) {
            return;
        }

        $touchedSourceIds   = [];
        $touchedCampaignIds = [];

        foreach ($shares as $share) {
            /** @var TrafficSource|null $trafficSource */
            $trafficSource = $trafficSources->get($share['type']->value);

            if (!$trafficSource) {
                continue;
            }

            $campaignId = $share['campaign_ref']
                ? $this->resolveCampaignId($trafficSource, $share['campaign_ref'])
                : null;

            $model->trafficSources()->attach($trafficSource->id, [
                'share'                      => round($share['share'], 2),
                'traffic_source_campaign_id' => $campaignId,
                'attribution_model'          => $attributionModel,
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
            if (!preg_match('/^\d{1,20}$/', $reference)) {
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

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

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
     * Resolves the parsed marketing touches into shop traffic sources and campaigns and syncs the
     * calculated attribution share onto the given model's `trafficSources` morph-to-many relationship.
     *
     * `model_has_traffic_sources` holds at most one row per (model, traffic source), so the per-campaign
     * shares returned by the attribution model are summed back up per traffic source before being written.
     * Without this the second campaign of a source would overwrite the first and the model would end up
     * carrying only a fraction of the credit it was actually awarded.
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

        $pivots = [];

        foreach ($shares as $share) {
            /** @var TrafficSource|null $trafficSource */
            $trafficSource = $trafficSources->get($share['type']->value);

            if (!$trafficSource) {
                continue;
            }

            $campaignId = null;
            if ($share['campaign_ref']) {
                $campaignId = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)
                    ->where('reference', $share['campaign_ref'])
                    ->value('id');
            }

            if (!isset($pivots[$trafficSource->id])) {
                $pivots[$trafficSource->id] = [
                    'share'                      => 0.0,
                    'traffic_source_campaign_id' => $campaignId,
                    'attribution_model'          => $attributionModel,
                ];
            } elseif ($pivots[$trafficSource->id]['traffic_source_campaign_id'] !== $campaignId) {
                $pivots[$trafficSource->id]['traffic_source_campaign_id'] = null;
            }

            $pivots[$trafficSource->id]['share'] += $share['share'];
        }

        if (empty($pivots)) {
            return;
        }

        foreach ($pivots as $trafficSourceId => $pivot) {
            $pivots[$trafficSourceId]['share'] = round($pivot['share'], 2);
        }

        $model->trafficSources()->syncWithoutDetaching($pivots);

        foreach ($trafficSources->whereIn('id', array_keys($pivots)) as $trafficSource) {
            TrafficSourceHydrateCustomers::dispatch($trafficSource);
        }
    }
}

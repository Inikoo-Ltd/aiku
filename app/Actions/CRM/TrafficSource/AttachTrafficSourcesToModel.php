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

        foreach ($shares as $share) {
            /** @var TrafficSource|null $trafficSource */
            $trafficSource = $trafficSources->get($share['type']->value);

            if (!$trafficSource) {
                continue;
            }

            $campaign = null;
            if ($share['campaign_ref']) {
                $campaign = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)
                    ->where('reference', $share['campaign_ref'])
                    ->first();
            }

            $model->trafficSources()->syncWithoutDetaching([
                $trafficSource->id => [
                    'share'                      => $share['share'],
                    'traffic_source_campaign_id' => $campaign?->id,
                    'attribution_model'          => $attributionModel,
                ],
            ]);

            TrafficSourceHydrateCustomers::dispatch($trafficSource);
        }
    }
}

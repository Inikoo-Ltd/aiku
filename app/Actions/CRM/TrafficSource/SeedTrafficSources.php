<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 15:50:57 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedTrafficSources
{
    use AsAction;

    public function handle(Shop $shop): void
    {

        $types=TrafficSource::where('shop_id', $shop->id)->pluck('type')->toArray();

        // Get all valid types from TrafficSourcesTypeEnum
        $validTypes = TrafficSourcesTypeEnum::values();

        // Find types that are not in the enum and delete them
        foreach ($types as $type) {
            if (!in_array($type, $validTypes)) {
                TrafficSource::where('shop_id', $shop->id)
                    ->where('type', $type)
                    ->delete();
            }
        }


        foreach (TrafficSourcesTypeEnum::cases() as $case) {

            $status = $case->status()[$case->value];
            $name = $case->labels()[$case->value];
            $type= $case->value;



            $trafficSource = TrafficSource::updateOrCreate(
                [
                    'type'            => $type,
                    'group_id'        => $shop->group_id,
                    'organisation_id' => $shop->organisation_id,
                    'shop_id'         => $shop->id,
                ],
                [
                    'name'   => $name,
                    'status' => $status,
                ]
            );
            $trafficSource->stats()->updateOrCreate([
                'traffic_source_id' => $trafficSource->id,
            ]);
        }
        $shop->crmStats()->update(
            ['number_traffic_sources' => TrafficSource::where('shop_id', $shop->id)->count()]
        );
        $shop->organisation->crmStats()->update(
            ['number_traffic_sources' => TrafficSource::where('organisation_id', $shop->organisation_id)->count()]
        );
        $shop->group->crmStats()->update(
            ['number_traffic_sources' => TrafficSource::where('group_id', $shop->group_id)->count()]
        );
    }

    public function getCommandSignature(): string
    {
        return 'traffic-source:seed';
    }

    public function asCommand(): void
    {
        foreach(Shop::all() as $shop) {
            $this->handle($shop);
        }

    }

}

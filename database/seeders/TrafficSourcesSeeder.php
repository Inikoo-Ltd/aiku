<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 26 Aug 2021 04:30:57 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace Database\Seeders;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Seeder;
use App\Models\CRM\TrafficSource;
use Illuminate\Support\Str;

class TrafficSourcesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Organic Google'    => ['google'],
            'Google Ads'        => ['gad_source', 'gclid', 'utm_source=google', 'utm_medium=cpc'],
            'Organic Bing'      => ['bing'],
            'Bing Ads'          => ['msclkid', 'utm_source=bing', 'utm_medium=cpc'],
            'Organic Facebook'  => ['facebook'],
            'Meta Ads'          => ['fbclid', 'utm_source=facebook', 'utm_medium=cpc'],
            'Organic Instagram' => ['instagram'],
            'Organic Pinterest' => ['pin', 'pinterest'],
            'Pinterest Ads'     => ['pp=0', 'pp=1', 'utm_source=pinterest', 'utm_medium=cpc'],
            'Organic TikTok'    => ['tiktok'],
            'TikTok Ads'        => ['ttclid', 'utm_source=tiktok', 'utm_medium=cpc'],
            'Organic LinkedIn'  => ['linkedin'],
            'LinkedIn Ads'      => ['li_fat_id', 'utm_source=linkedin', 'utm_medium=cpc'],
        ];

        $shops = Shop::all();

        foreach ($shops as $shop) {
            foreach ($data as $name => $urlPatterns) {
                $trafficSource = TrafficSource::updateOrCreate(
                    [
                        'name'            => $name,
                        'group_id'        => $shop->group_id,
                        'organisation_id' => $shop->organisation_id,
                        'shop_id'         => $shop->id,
                    ],
                    [
                        'type'            => Str::slug($name),
                        'settings'        => [
                            'url_patterns' => $urlPatterns,
                        ],
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
    }
}

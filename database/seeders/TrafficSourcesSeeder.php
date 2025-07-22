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

class TrafficSourcesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Organic Google' => ['google'],
            'Google Ads' => ['gad_source', 'gclid'],
            'Organic Bing' => ['bing'],
            'Bing Ads' => ['msclkid'],
            'Organic Facebook' => ['facebook'],
            'Meta Ads' => ['fbclid'],
            'Organic Instagram' => ['instagram'],
            'Organic Pinterest' => ['pin', 'pinterest'],
            'Pinterest Ads' => ['pp=0', 'pp=1'],
            'Organic TikTok' => ['tiktok'],
            'TikTok Ads' => ['ttclid'],
            'Organic LinkedIn' => ['linkedin'],
            'LinkedIn Ads' => ['li_fat_id'],
        ];

        $shops = Shop::all();

        foreach ($shops as $shop) {
            foreach ($data as $name => $urlPatterns) {
                $trafficSource = TrafficSource::updateOrCreate(
                    [
                        'name' => $name,
                        'group_id' => $shop->group_id,
                        'organisation_id' => $shop->organisation_id,
                        'shop_id' => $shop->id,
                    ],
                    [
                        'settings' => [
                            'url_patterns' => $urlPatterns,
                        ],
                    ]
                );
                $trafficSource->stats()->updateOrCreate([
                    'traffic_source_id' => $trafficSource->id,
                ]);
            }
            $shop->crmStats()->updateOrCreate(
                ['shop_id' => $shop->id],
                ['number_traffic_sources' => TrafficSource::where('shop_id', $shop->id)->count()]
            );
            $shop->organisation->crmStats()->updateOrCreate(
                ['organisation_id' => $shop->organisation_id],
                ['number_traffic_sources' => TrafficSource::where('organisation_id', $shop->organisation_id)->count()]
            );
            $shop->group->crmStats()->updateOrCreate(
                ['group_id' => $shop->group_id],
                ['number_traffic_sources' => TrafficSource::where('group_id', $shop->group_id)->count()]
            );
        }
    }
}

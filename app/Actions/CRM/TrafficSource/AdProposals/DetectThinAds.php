<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Responsive search ads running on far fewer headlines than they are allowed.
 *
 * Google assembles each impression from the headlines an ad carries, so four of a possible fifteen is
 * four fifteenths of the material and a correspondingly thin ad strength. This is the rare case where
 * the weakness is countable rather than a matter of taste: nobody has to judge whether the copy is
 * good to see that there is not enough of it.
 *
 * On one live account 49 of 340 ads sit below the bar and 15 of those carry only three headlines,
 * which is the minimum Google will accept at all.
 */
class DetectThinAds
{
    use AsAction;

    /** Google allows fifteen. Below eight there is visibly little for it to test. */
    private const int MIN_HEADLINES = 8;

    /**
     * Only ads that are actually serving. Adding copy to a paused ad changes nothing today, and the
     * queue is for things worth doing now.
     */
    private const string SERVING = 'ENABLED';

    /**
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @return array<int, array>
     */
    public function handle(Collection $campaigns): array
    {
        $candidates = [];

        foreach ($campaigns as $campaign) {
            foreach (Arr::get($campaign->data, 'ad_groups', []) as $group) {
                if (Arr::get($group, 'status') !== self::SERVING) {
                    continue;
                }

                foreach (Arr::get($group, 'ads', []) as $ad) {
                    $headlines = Arr::get($ad, 'headlines', []);

                    if (
                        Arr::get($ad, 'type') !== 'RESPONSIVE_SEARCH_AD'
                        || Arr::get($ad, 'status') !== self::SERVING
                        || count($headlines) >= self::MIN_HEADLINES
                        || count($headlines) === 0
                    ) {
                        continue;
                    }

                    $candidates[] = [
                        'type'        => AdProposalTypeEnum::STRENGTHEN_AD,
                        'campaign_id' => $campaign->id,
                        'fingerprint' => AdProposalFingerprint::run(
                            AdProposalTypeEnum::STRENGTHEN_AD,
                            [$campaign->reference, Arr::get($group, 'id'), Arr::get($ad, 'id')]
                        ),
                        'subject' => implode(' / ', array_slice($headlines, 0, 3)),
                        'payload' => [
                            'ad_group_id' => (string) Arr::get($group, 'id'),
                            'ad_id'       => (string) Arr::get($ad, 'id'),

                            /* Filled in later by the drafting step, which needs the existing copy to
                               write anything worth reading. */
                            'headlines' => [],
                        ],
                        'evidence' => [
                            'campaign'           => $campaign->name,
                            'ad_group'           => Arr::get($group, 'name'),
                            'headline_count'     => count($headlines),
                            'headline_allowance' => 15,
                            'headlines'          => $headlines,
                            'descriptions'       => Arr::get($ad, 'descriptions', []),
                            'final_urls'         => Arr::get($ad, 'final_urls', []),
                            'currency'           => Arr::get($campaign->data, 'currency'),
                            'rule'               => 'this ad is using '.count($headlines).' of the 15 headlines Google allows',
                        ],

                        /* No spend attaches to a single ad in what is stored, so the ranking is how
                           much room there is to improve: the emptiest ads first. */
                        'amount' => 0,
                        'rank'   => 15 - count($headlines),
                    ];
                }
            }
        }

        return $candidates;
    }
}

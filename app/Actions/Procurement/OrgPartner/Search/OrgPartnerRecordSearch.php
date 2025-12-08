<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgPartner\Search;

use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgPartnerRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(OrgPartner $orgPartner): void
    {

        $partner = $orgPartner->partner;

        if (! $partner) {
            echo "org partner doesn't have organisation\n";

            return;
        }

        $modelData = [
            'group_id' => $orgPartner->group_id,
            'organisation_id' => $orgPartner->organisation_id,
            'organisation_slug' => $orgPartner->organisation->slug,
            'sections' => ['procurement'],
            'haystack_tier_1' => trim($partner->code.' '.$partner->name),
            'haystack_tier_2' => trim($partner->email),
            'result' => [
                'route' => [
                    'name' => 'grp.org.procurement.org_partners.show',
                    'parameters' => [
                        $orgPartner->organisation->slug,
                        $orgPartner->id,
                    ],
                ],
                'description' => [
                    'label' => $partner->name,
                ],
                'code' => [
                    'label' => $partner->code,
                ],
                'icon' => [
                    'icon' => 'fal fa-users-class',
                ],
                'meta' => [
                    [
                        'label' => $partner->email,
                        'tooltip' => __('Email'),
                    ],
                ],
            ],
        ];

        $orgPartner->universalSearch()->updateOrCreate(
            [],
            $modelData
        );

    }
}

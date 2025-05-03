<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\HumanResources\Workplace\Search;

use App\Models\HumanResources\Workplace;
use Lorisleiva\Actions\Concerns\AsAction;

class WorkplaceRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Workplace $workplace): void
    {
        if ($workplace->trashed()) {
            if ($workplace->universalSearch) {
                $workplace->universalSearch()->delete();
            }

            return;
        }

        $workplace->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $workplace->group_id,
                'organisation_id'   => $workplace->organisation_id,
                'organisation_slug' => $workplace->organisation->slug,
                'sections'          => ['hr'],
                'haystack_tier_1'   => $workplace->name,
                'result'            => [
                    'route'      => [
                        'name'       => 'grp.org.hr.workplaces.show',
                        'parameters' => [
                            'organisation' => $workplace->organisation->slug,
                            'workplace'     => $workplace->slug,
                        ]
                    ],
                    'code' => [
                        'label' => $workplace->name,
                    ],
                    'icon'       => [
                        'icon' => 'fal fa-building'
                    ]
                ]
            ]
        );
    }


}

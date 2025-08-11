<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\Search;

use App\Models\Masters\MasterCollection;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(MasterCollection $masterCollection): void
    {
        if ($masterCollection->trashed()) {
            $masterCollection->universalSearch()?->delete();

            return;
        }

        $masterCollection->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $masterCollection->group_id,
                'sections'          => ['catalogue'],
                'haystack_tier_1'   => $masterCollection->name,
                'haystack_tier_2'   => $masterCollection->code,
                'result'            => [
                    'route'         => [
                        'name'          => 'grp.overview.catalogue.master-collections.show',
                            'parameters'    => [
                                $masterCollection->slug,
                            ]
                    ],
                    'code' => [
                        'label' => $masterCollection->code,
                    ],
                    'description' => [
                        'label' => $masterCollection->name,
                    ],
                    'icon' => [
                        'icon' => 'fal fa-album-collection',
                    ],
                ]
            ]
        );
    }
}
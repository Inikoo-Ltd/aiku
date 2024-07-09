<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 10:02:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Manufacturing\RawMaterial\Hydrators;

use App\Models\Manufacturing\RawMaterial;
use Lorisleiva\Actions\Concerns\AsAction;

class RawMaterialHydrateUniversalSearch
{
    use AsAction;
    public string $jobQueue = 'universal-search';

    public function handle(RawMaterial $rawMaterial): void
    {
        $rawMaterial->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $rawMaterial->group_id,
                'organisation_id'   => $rawMaterial->organisation_id,
                'organisation_slug' => $rawMaterial->organisation->slug,
                'section'           => 'manufacture',
                'title'             => trim($rawMaterial->code.' '.$rawMaterial->description),
                'description'       => ''
            ]
        );
    }

}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Apr 2024 17:59:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateCollectionCategories implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_collection_categories' => $organisation->collectionCategories()->count(),
        ];

        $organisation->catalogueStats()->update($stats);
    }


}

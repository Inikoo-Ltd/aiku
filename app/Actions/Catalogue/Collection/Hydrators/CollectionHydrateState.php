<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Jun 2025 12:38:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Collection\CollectionProductStatusEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\CollectionStats;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateState implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Collection $collection): string
    {
        return $collection->id;
    }


    public function handle(Collection $collection): void
    {
        if ($collection->state == CollectionStateEnum::INACTIVE) {
            return;
        }

        $data = $this->getCollectionStateFromChildren($collection->stats);

        $collection->update($data);
    }


    public function getCollectionStateFromChildren(CollectionStats $stats): array
    {
        if ($stats->number_products_state_active > 0 || $stats->number_families_state_active > 0) {
            return [
                'state' => CollectionStateEnum::ACTIVE
            ];
        }

        if ($stats->number_products_state_discontinuing > 0 || $stats->number_families_state_discontinuing > 0) {
            return [
                'product_state' => CollectionProductStatusEnum::DISCONTINUING
            ];
        }

        if ($stats->number_products_state_discontinued == 0 && $stats->number_families_state_discontinued == 0 && $stats->number_families_state_inactive == 0) {
            return [
               'state' => CollectionStateEnum::IN_PROCESS
            ];
        }

        return [
                'state' => CollectionProductStatusEnum::DISCONTINUED
            ];
    }


}

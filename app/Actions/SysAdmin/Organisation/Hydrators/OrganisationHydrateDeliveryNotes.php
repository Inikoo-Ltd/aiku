<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Sept 2024 21:29:12 Malaysia Time, Taipei, Taiwan
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public function getJobUniqueId($organisationId, DeliveryNoteTypeEnum $type): string
    {
        return $organisationId.'-'.$type->value;
    }

    public function handle($organisationId, DeliveryNoteTypeEnum $type): void
    {

        $organisation = Organisation::find($organisationId);
        if (! $organisation) {
            return;
        }

        if ($type == DeliveryNoteTypeEnum::ORDER) {
            $stats = $this->getStoreDeliveryNotesStats($organisation);
        } else {
            $stats = $this->getStoreReplacementsStats($organisation);
        }

        $organisation->orderingStats()->update($stats);
    }
}

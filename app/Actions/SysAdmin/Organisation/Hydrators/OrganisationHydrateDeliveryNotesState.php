<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Nov 2025 14:53:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateDeliveryNotesState implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public function getJobUniqueId(int $organisationId, DeliveryNoteStateEnum $state): string
    {
        return $organisationId.'-'.$state->value;
    }

    public function handle(int $organisationId, DeliveryNoteStateEnum $state): void
    {
        $organisation = Organisation::find($organisationId);
        if (!$organisation) {
            return;
        }

        $stats = $this->getDeliveryStateNotesStats($state, $organisation);
        $organisation->orderHandlingStats()->update($stats);
    }


}

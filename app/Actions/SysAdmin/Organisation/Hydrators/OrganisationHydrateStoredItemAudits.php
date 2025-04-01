<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 20:34:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateStoredItemAudits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_stored_item_audits' => StoredItemAudit::where('organisation_id', $organisation->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'stored_item_audits',
            field: 'state',
            enum: StoredItemAuditStateEnum::class,
            models: StoredItemAudit::class,
            where: function ($q) use ($organisation) {
                $q->where('organisation_id', $organisation->id);
            }
        ));

        $organisation->fulfilmentStats()->update($stats);
    }
}

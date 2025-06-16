<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 11:09:01 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\StoredItemAudit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydrateStoredItemAudits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Fulfilment $fulfilment): string
    {
        return $fulfilment->id;
    }

    public function handle(Fulfilment $fulfilment): void
    {
        $stats = [
            'number_stored_item_audits' => StoredItemAudit::where('fulfilment_id', $fulfilment->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'stored_item_audits',
            field: 'state',
            enum: StoredItemAuditStateEnum::class,
            models: StoredItemAudit::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id);
            }
        ));

        $fulfilment->stats()->update($stats);
    }
}

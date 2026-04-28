<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 28 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateCurrentBatchCodes implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(OrgStock $orgStock): int
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $orgStock->update([
            'current_batch_codes' => $orgStock->batchCodes()->count(),
        ]);
    }
}

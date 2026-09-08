<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 28 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class OrgStockHydrateCurrentBatchCodes implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-current-batch-codes {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

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

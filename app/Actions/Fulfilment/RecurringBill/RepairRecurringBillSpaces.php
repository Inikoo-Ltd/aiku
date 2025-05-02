<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Apr 2025 18:55:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill;

use App\Actions\HydrateModel;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\RecurringBill;
use Illuminate\Support\Collection;

class RepairRecurringBillSpaces extends HydrateModel
{
    public string $commandSignature = 'recurring_bills:repair_spaces {organisations?*} {--s|slugs=}';

    public function handle(RecurringBill $recurringBill): void
    {
        FindSpacesAndAttachThemToNewRecurringBill::make()->action($recurringBill);
    }

    protected function getModel(string $slug): RecurringBill
    {
        return RecurringBill::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->get();
    }
}

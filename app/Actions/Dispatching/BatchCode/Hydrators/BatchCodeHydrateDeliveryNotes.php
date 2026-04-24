<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 24 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\Hydrators;

use App\Models\Dispatching\BatchCode;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class BatchCodeHydrateDeliveryNotes implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(BatchCode $batchCode): int
    {
        return $batchCode->id;
    }

    public function handle(BatchCode $batchCode): void
    {
        $count = $batchCode->deliveryNoteItems()
            ->distinct('delivery_note_id')
            ->count('delivery_note_id');

        $batchCode->update(['number_delivery_notes' => $count]);
    }
}

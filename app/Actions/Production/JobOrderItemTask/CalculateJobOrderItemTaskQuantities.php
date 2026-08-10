<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItemTask;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateJobOrderItemTaskQuantities
{
    use AsAction;

    public function handle(JobOrderItemTask $jobOrderItemTask): JobOrderItemTask
    {
        $quantityMade     = $jobOrderItemTask->sessions()
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->sum('quantity_made');
        $quantityRejected = $jobOrderItemTask->sessions()
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->sum('quantity_rejected');

        $state = JobOrderItemTaskStateEnum::TODO;
        if ($quantityMade >= $jobOrderItemTask->quantity_required) {
            $state = JobOrderItemTaskStateEnum::DONE;
        } elseif ($jobOrderItemTask->sessions()->whereIn('state', [
            ManufactureTaskSessionStateEnum::OPEN,
            ManufactureTaskSessionStateEnum::CLOSED,
        ])->exists()) {
            $state = JobOrderItemTaskStateEnum::IN_PROGRESS;
        }

        $jobOrderItemTask->update([
            'quantity_made'     => $quantityMade,
            'quantity_rejected' => $quantityRejected,
            'state'             => $state,
        ]);

        return $jobOrderItemTask;
    }
}

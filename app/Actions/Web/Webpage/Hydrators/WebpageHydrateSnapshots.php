<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 12:34:58 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateSnapshots implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Webpage $webpage): string
    {
        return $webpage->id;
    }

    public function handle(Webpage $webpage): void
    {
        $stats = [
            'number_snapshots' => $webpage->snapshots()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'snapshots',
                field: 'state',
                enum: SnapshotStateEnum::class,
                models: Snapshot::class,
                where: function ($q) use ($webpage) {
                    $q->where('parent_id', $webpage->id);
                    $q->where('parent_id', $webpage->id);
                }
            )
        );


        $webpage->stats()->update($stats);
    }


}

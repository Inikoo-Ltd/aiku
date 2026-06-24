<?php

/*
 * Author Louis Perez
 * Created on 19-06-2026-09h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Barcode\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Models\Helpers\Barcode;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateBarcodes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $commandSignature = 'hydrate:barcodes';

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }
    public function handle(Group $group): void
    {
        $stats = [
            'number_barcodes' => $group->barcodes()->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'barcodes',
                field: 'status',
                enum: BarcodeStatusEnum::class,
                models: Barcode::class,
                where: function ($q) {
                    $q->where('group_id', group()->id);
                }
            )
        );

        $group->goodsStats()->update($stats);
    }

    public function asCommand()
    {
        $this->handle(group());
    }
}

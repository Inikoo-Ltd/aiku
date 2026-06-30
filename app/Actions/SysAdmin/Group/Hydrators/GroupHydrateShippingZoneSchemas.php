<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateShippingZoneSchemas implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return (string) $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_shipping_zone_schemas' => $group->shippingZoneSchemas()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'shipping_zone_schemas',
                field: 'state',
                enum: ShippingZoneSchemaStateEnum::class,
                models: ShippingZoneSchema::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->catalogueStats()->update($stats);
    }
}

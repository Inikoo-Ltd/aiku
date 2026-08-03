<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateShippingZoneSchemas implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return (string) $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_shipping_zone_schemas' => $organisation->shippingZoneSchemas()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'shipping_zone_schemas',
                field: 'state',
                enum: ShippingZoneSchemaStateEnum::class,
                models: ShippingZoneSchema::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        $organisation->catalogueStats()->update($stats);
    }
}

<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Jan 2024 16:42:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\SysAdmin\Organisation;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydratePallets
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    private Organisation $organisation;
    public function __construct(Organisation $organisation)
    {
        $this->organisation = $organisation;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->organisation->id))->dontRelease()];
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_pallets' => Pallet::where('organisation_id', $organisation->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($organisation) {
                $q->where('organisation_id', $organisation->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($organisation) {
                $q->where('organisation_id', $organisation->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($organisation) {
                $q->where('organisation_id', $organisation->id);
            }
        ));

        $organisation->fulfilmentStats()->update($stats);
    }
}

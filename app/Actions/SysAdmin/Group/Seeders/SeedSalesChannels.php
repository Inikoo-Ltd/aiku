<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Ordering\SalesChannel\StoreSalesChannel;
use App\Actions\Ordering\SalesChannel\UpdateSalesChannel;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedSalesChannels
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(Group $group): void
    {
        foreach (SalesChannelTypeEnum::cases() as $salesChannelType) {
            if ($salesChannelType->canSeed()) {
                if ($salesChannel = $group->salesChannels()->where('type', $salesChannelType->value)->first()) {
                    UpdateSalesChannel::make()->action(
                        $salesChannel,
                        [
                            'code' => $salesChannelType->value,
                            'name' => $salesChannelType->labels()[$salesChannelType->value],
                        ]
                    );
                } else {
                    StoreSalesChannel::make()->action(
                        $group,
                        [
                            'type' => $salesChannelType,
                            'code' => $salesChannelType->value,
                            'name' => $salesChannelType->labels()[$salesChannelType->value],
                            'is_seeded' => true,
                        ],
                        strict: false
                    );
                }
            }
        }
    }

    public string $commandSignature = 'group:seed_sales_channels';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding sales channels for group: $group->name");
            $this->handle($group);
        }

        return 0;
    }
}

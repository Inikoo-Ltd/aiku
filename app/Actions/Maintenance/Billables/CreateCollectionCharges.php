<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Dec 2025 19:42:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Billables;

use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\Rules\WithStoreOfferRules;
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateCollectionCharges extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;
    use WithStoreOfferRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ?Charge
    {
        data_set($modelData, 'type', ChargeTypeEnum::COLLECTION);
        data_set($modelData, 'trigger', ChargeTriggerEnum::ORDER);
        data_set($modelData, 'code', 'Col');
        data_set($modelData, 'status', true);
        data_set($modelData, 'state', ChargeStateEnum::ACTIVE);

        return StoreCharge::make()->action($shop, $modelData);
    }


    public function getCommandSignature(): string
    {
        return 'create_collection_charge {shop} {amount} {name} {description}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $modelData = [
            'name'         => $command->argument('name'),
            'description'  => $command->argument('description'),
            'trigger_data' => [
                'order_number' => 1,
                'min_amount'   => $command->argument('amount'),
            ],
            'settings'     => [

                    'amount' => $command->argument('amount'),

            ]
        ];

        $charge = $this->handle($shop, $modelData);

        if ($charge) {
            $command->info('Charge created: '.$charge->name.' ('.$charge->code.')');
        } else {
            $command->error('Charge could not be created');
        }

        return 0;
    }

}

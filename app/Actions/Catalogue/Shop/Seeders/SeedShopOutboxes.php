<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 19:31:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Seeders;

use App\Actions\Comms\Outbox\StoreOutbox;
use App\Actions\Comms\Outbox\UpdateOutbox;
use App\Actions\Traits\WithOutboxBuilder;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedShopOutboxes
{
    use AsAction;
    use WithOutboxBuilder;


    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop): void
    {
        foreach (OutboxCodeEnum::cases() as $case) {
            if (in_array('Shop', $case->scope()) && in_array($shop->type->value, $case->shopTypes())) {
                $postRoom    = PostRoom::where('code', $case->postRoomCode()->value)->first();
                /** @var OrgPostRoom $orgPostRoom */
                $orgPostRoom = $postRoom->orgPostRooms()->where('organisation_id', $shop->organisation->id)->first();

                /** @var Outbox $outbox */
                if ($outbox = Outbox::where('shop_id', $shop->id)->where('code', $case)->first()) {
                    $dataToUpdate = [
                        'name' => $case->label(),
                    ];
                    if ($shop->type == ShopTypeEnum::FULFILMENT) {
                        data_set($dataToUpdate, 'fulfilment_id', $shop->fulfilment->id);
                    }

                    UpdateOutbox::make()->action(
                        $outbox,
                        $dataToUpdate
                    );
                } else {
                    $outbox = StoreOutbox::make()->action(
                        $orgPostRoom,
                        $shop,
                        [
                            'name'       => $case->label(),
                            'code'       => $case,
                            'type'       => $case->type(),
                            'state'      => $case->defaultState(),
                            'model_type' => $case->modelType(),
                            'builder'    => $this->getDefaultBuilder($case, $shop)

                        ]
                    );
                }

                $this->setEmailOngoingRuns($outbox, $case, $shop);
            }
        }
    }

    public string $commandSignature = 'shop:seed_outboxes';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $command->info("Seeding shop outboxes");
        foreach (Shop::all() as $shop) {
            setPermissionsTeamId($shop->group_id);
            $this->handle($shop);
        }

        return 0;
    }


}

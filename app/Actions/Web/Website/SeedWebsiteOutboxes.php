<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Jul 2024 17:26:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Comms\Outbox\StoreOutbox;
use App\Actions\Comms\Outbox\UpdateOutbox;
use App\Actions\Traits\WithOutboxBuilder;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\Web\Website;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedWebsiteOutboxes
{
    use AsAction;
    use WithOutboxBuilder;

    /**
     * @throws \Throwable
     */
    public function handle(Website $website): void
    {
        foreach (OutboxCodeEnum::cases() as $case) {
            if (in_array('Website', $case->scope()) && in_array($website->shop->type->value, $case->shopTypes())) {
                $postRoom    = PostRoom::where('code', $case->postRoomCode()->value)->first();
                /** @var OrgPostRoom $orgPostRoom */
                $orgPostRoom = $postRoom->orgPostRooms()->where('organisation_id', $website->organisation->id)->first();


                if ($outbox = Outbox::where('website_id', $website->id)->where('code', $case)->first()) {

                    $dataToUpdate = [
                        'name' => $case->label(),
                    ];

                    if ($website->type == WebsiteTypeEnum::FULFILMENT) {
                        $dataToUpdate['fulfilment_id'] = $website->shop->fulfilment->id;
                    }

                    UpdateOutbox::make()->action(
                        $outbox,
                        $dataToUpdate
                    );
                } else {
                    $outbox = StoreOutbox::make()->action(
                        $orgPostRoom,
                        $website,
                        [
                            'name'       => $case->label(),
                            'code'       => $case,
                            'type'       => $case->type(),
                            'state'      => $case->defaultState(),
                            'model_type' => $case->modelType(),
                            'builder'    => $this->getDefaultBuilder($case, $website)

                        ]
                    );
                }


                $this->setEmailOngoingRuns($outbox, $case, $website);
            }
        }
    }

    public string $commandSignature = 'website:seed_outboxes {website? : The website slug}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('website') == null) {
            $websites = Website::all();
            foreach ($websites as $website) {
                $this->handle($website);
            }

            return 0;
        }

        try {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($website);

        return 0;
    }


}

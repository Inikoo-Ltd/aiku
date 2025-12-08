<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:46:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Seeders;

use App\Actions\Comms\Outbox\StoreOutbox;
use App\Actions\Comms\Outbox\UpdateOutbox;
use App\Actions\SysAdmin\Organisation\WithOrganisationCommand;
use App\Actions\Traits\WithOutboxBuilder;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedOrganisationOutboxes
{
    use AsAction;
    use WithOrganisationCommand;
    use WithOutboxBuilder;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation): void
    {
        if ($organisation->type == OrganisationTypeEnum::SHOP or $organisation->type == OrganisationTypeEnum::DIGITAL_AGENCY) {
            foreach (OutboxCodeEnum::cases() as $case) {
                if (in_array('Organisation', $case->scope())) {
                    $postRoom = $organisation->group->postRooms()->where('code', $case->postRoomCode())->first();

                    $orgPostRoom = $postRoom->orgPostRooms()->where('organisation_id', $organisation->id)->first();

                    if ($outbox = $organisation->outboxes()->where('code', $case)->first()) {
                        UpdateOutbox::make()->action(
                            $outbox,
                            [
                                'name' => $case->label(),
                            ]
                        );
                    } else {
                        $outbox = StoreOutbox::make()->action(
                            $orgPostRoom,
                            $organisation,
                            [
                                'name' => $case->label(),
                                'code' => $case,
                                'type' => $case->type(),
                                'state' => $case->defaultState(),
                                'model_type' => $case->modelType(),
                                'builder' => $this->getDefaultBuilder($case, $organisation),

                            ]
                        );
                    }
                    $this->setEmailOngoingRuns($outbox, $case, $organisation);
                }
            }
        }
    }

    public string $commandSignature = 'org:seed_outboxes {organisation? : The organisation slug}';
}

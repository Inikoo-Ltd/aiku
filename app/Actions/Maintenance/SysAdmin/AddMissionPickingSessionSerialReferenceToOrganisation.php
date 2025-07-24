<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 10:04:20 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\SysAdmin;

use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class AddMissionPickingSessionSerialReferenceToOrganisation
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP)->get();

        /** @var Organisation $organisation */
        foreach ($organisations as $organisation) {
            $organisation->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::PICKING_SESSION,
                    'organisation_id' => $organisation->id,
                    'format'          => 'PS'.$organisation->slug.'-%04d'
                ]
            );
        }
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:add_mission_picking_session_serial_reference_to_organisation';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}

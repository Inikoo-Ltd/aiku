<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:46:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Seeders;

use App\Actions\Comms\OrgPostRoom\StoreOrgPostRoom;
use App\Actions\Comms\OrgPostRoom\UpdateOrgPostRoom;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\WithOrganisationCommand;
use App\Models\SysAdmin\Organisation;

class SeedOrgPostRooms extends OrgAction
{
    use WithOrganisationCommand;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation): void
    {
        foreach ($organisation->group->postRooms as $postRoom) {
            $orgPostRoom = $organisation->orgPostRooms()->where('type', $postRoom->code->value)->first();

            if ($orgPostRoom) {
                UpdateOrgPostRoom::make()->action(
                    $orgPostRoom,
                    [
                        'name' => $postRoom->name,
                    ]
                );
            } else {
                StoreOrgPostRoom::make()->action(
                    $postRoom,
                    $organisation,
                    []
                );
            }
        }
    }

    public string $commandSignature = 'org:seed_org_post_rooms {organisation? : The organisation slug}';
}

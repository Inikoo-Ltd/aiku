<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\OrgPostRoom;

use App\Actions\Comms\PostRoom\Hydrators\PostRoomHydrateOrgPostRooms;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrgPostRooms;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgPostRooms;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;

class StoreOrgPostRoom extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(PostRoom $postRoom, Organisation $organisation, array $modelData): OrgPostRoom
    {
        $orgPostRoom = DB::transaction(function () use ($organisation, $postRoom, $modelData) {

            data_set($modelData, 'group_id', $postRoom->group_id);
            data_set($modelData, 'post_room_id', $postRoom->id);
            data_set($modelData, 'type', $postRoom->code->value);
            data_set($modelData, 'name', $postRoom->name);


            /** @var OrgPostRoom $orgPostRoom */
            $orgPostRoom = $organisation->orgPostRooms()->create($modelData);
            $orgPostRoom->stats()->create();

            return $orgPostRoom;
        });
        GroupHydrateOrgPostRooms::dispatch($orgPostRoom->group);
        OrganisationHydrateOrgPostRooms::dispatch($orgPostRoom->organisation);
        PostRoomHydrateOrgPostRooms::dispatch($postRoom);

        return $orgPostRoom;
    }


    public function rules(): array
    {
        return [
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(PostRoom $postRoom, Organisation $organisation, array $modelData): OrgPostRoom
    {
        $this->initialisation($organisation, $modelData);

        return $this->handle($postRoom, $organisation, $this->validatedData);
    }
}

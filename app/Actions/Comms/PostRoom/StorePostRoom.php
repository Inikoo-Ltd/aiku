<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\PostRoom;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePostRooms;
use App\Enums\Comms\PostRoom\PostRoomCodeEnum;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StorePostRoom extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Group $group, array $modelData): PostRoom
    {
        $postRoom = DB::transaction(function () use ($group, $modelData) {
            /** @var PostRoom $postRoom */
            $postRoom = $group->postRooms()->create($modelData);
            $postRoom->stats()->create();

            return $postRoom;
        });
        GroupHydratePostRooms::dispatch($group);

        return $postRoom;
    }


    public function rules(): array
    {
        return [
            'code' => [
                'required',
                new IUnique(
                    table: 'post_rooms',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
                Rule::enum(PostRoomCodeEnum::class)
            ],
            'name' => ['required', 'string', 'max:250',],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Group $group, array $modelData): PostRoom
    {
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}

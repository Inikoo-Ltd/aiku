<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Comms\PostRoom\StorePostRoom;
use App\Actions\Comms\PostRoom\UpdatePostRoom;
use App\Actions\GrpAction;
use App\Enums\Comms\PostRoom\PostRoomCodeEnum;
use App\Models\SysAdmin\Group;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SeedPostRooms extends GrpAction
{
    use AsAction;

    public function handle(Group $group): void
    {
        foreach (PostRoomCodeEnum::cases() as $case) {
            $postRoom = $group->postRooms()->where('code', $case)->first();

            if ($postRoom) {
                UpdatePostRoom::make()->action(
                    $postRoom,
                    [
                        'name' => $case->label(),
                    ]
                );
            } else {
                try {
                    $code = $case->value;
                    StorePostRoom::make()->action(
                        $group,
                        [
                            'code' => $code,
                            'name' => $case->label(),
                        ]
                    );
                } catch (Exception|Throwable $e) {
                    echo $e->getMessage()."\n";
                }
            }
        }
    }

    public string $commandSignature = 'group:seed_post_rooms {group : group slug}';

    public function asCommand(Command $command): int
    {
        try {
            /** @var Group $group */
            $group = Group::where('slug', $command->argument('group'))->firstOrFail();
            $this->group = $group;
            app()->instance('group', $group);
            setPermissionsTeamId($group->id);
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($group);
        echo "Success seed group post rooms ✅ \n";

        return 0;
    }
}

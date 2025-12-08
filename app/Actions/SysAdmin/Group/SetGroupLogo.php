<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\Helpers\Media\StoreMediaFromIcon;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\SysAdmin\Group;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SetGroupLogo
{
    use AsAction;
    use WithAttachMediaToModel;

    public function handle(Group $group): Group
    {
        $media = StoreMediaFromIcon::run($group);
        $this->attachMediaToModel($group, $media, 'logo');
        $group->updateQuietly(
            [
                'image_id' => $media->id,
            ]
        );

        return $group;
    }

    public string $commandSignature = 'group:logo {group : Group slug}';

    public function asCommand(Command $command): int
    {

        try {
            $group = Group::where('slug', $command->argument('group'))->firstOrFail();
        } catch (Exception) {
            $command->error('Group not found');

            return 1;
        }

        try {
            $this->handle($group);

            return 0;
        } catch (Exception $exception) {
            $command->error('Error setting logo, '.$exception->getMessage());

            return 1;
        }
    }
}

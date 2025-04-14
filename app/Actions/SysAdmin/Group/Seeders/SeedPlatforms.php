<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Dropshipping\Platform\StorePlatform;
use App\Actions\Dropshipping\Platform\UpdatePlatform;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedPlatforms extends OrgAction
{
    use AsAction;
    use WithAttachMediaToModel;

    public function handle(Group $group): void
    {
        foreach (PlatformTypeEnum::cases() as $case) {
            $code = $case->value;

            if ($group->platforms()->where('code', $code)->exists()) {
                $platform = $group->platforms()->where('code', $code)->first();

                UpdatePlatform::make()->action($platform, [
                    'name' => $case->labels()[$case->value],
                ]);
            } else {
                StorePlatform::make()->action(
                    $group,
                    [
                        'code' => $code,
                        'name' => $case->labels()[$case->value],
                        'type' => $case
                    ]
                );
            }
        }
    }

    public string $commandSignature = 'group:seed-platforms';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $this->group = $group;
            app()->instance('group', $group);
            setPermissionsTeamId($group->id);
            $this->handle($group);
        }

        $command->line("Success seed the platforms ✅");

        return 0;
    }
}

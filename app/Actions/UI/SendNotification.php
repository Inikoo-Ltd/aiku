<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Dec 2023 22:22:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI;

use App\Events\BroadcastUserNotification;
use App\Models\SysAdmin\Group;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SendNotification
{
    use AsAction;

    public function handle(Group $group, string $title, string $text): void
    {
        BroadcastUserNotification::dispatch($group, $title, $text);
    }

    public string $commandSignature = 'send:notification {group} {message?}';

    public function asCommand(Command $command): int
    {
        try {
            $group = Group::where('slug', $command->argument('group'))->firstOrFail();
        } catch (Exception) {
            $command->error('Group not found');

            return 1;
        }

        if ($command->hasArgument('message')) {
            $title = 'Hey';
            $text = $command->argument('message');
        } else {
            $title = $command->ask('Title');
            $text = $command->ask('Text');
        }
        $this->handle($group, $title, $text);

        return 0;
    }
}

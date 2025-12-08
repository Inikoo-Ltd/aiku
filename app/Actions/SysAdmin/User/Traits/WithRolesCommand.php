<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 06 Dec 2023 22:05:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Traits;

use App\Models\SysAdmin\User;
use Exception;
use Illuminate\Console\Command;

trait WithRolesCommand
{
    public function asCommand(Command $command): int
    {
        $this->trusted = true;

        try {
            $user = User::where('slug', $command->argument('user'))->firstOrFail();
        } catch (Exception) {
            $command->error("User {$command->argument('user')} not found");

            return 1;
        }

        $this->fill([
            'role_names' => $command->argument('roles'),
        ]);

        $this->validateAttributes();
        $user = $this->handle($user, $this->get('roles'));

        $actionMessage = match ($command->getName()) {
            'user:sync-roles' => 'Roles synced',
            'user:add-roles' => 'Roles added',
            'user:remove-roles' => 'Roles removed',
        };

        $command->info("User <fg=yellow>$user->username</> $actionMessage: ".implode($command->argument('roles')).' 👍');

        return 0;
    }
}

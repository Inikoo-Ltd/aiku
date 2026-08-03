<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 11:50:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SetUserEmployedInOrganisation
{
    use AsAction;

    public function handle(User $user): void
    {
        $employee = Employee::where('user_id', $user->id)
            ->whereIn('state', ['working', 'leaving'])
            ->first();

        $user->update(['employed_in_organisation_id' => $employee?->organisation_id]);
    }

    public string $commandSignature = 'user:set-employed-organisation {user? : User slug}';

    public function asCommand(Command $command): int
    {
        $userSlug = $command->argument('user');

        if ($userSlug) {
            $user = User::where('slug', $userSlug)->first();
            if (! $user) {
                $command->error("User $userSlug not found");

                return 1;
            }
            $this->handle($user);
            $command->info("Updated user $user->username");
        } else {
            $command->info('Updating all users...');

            $employees = Employee::whereNotNull('user_id')
                ->whereIn('state', ['working', 'leaving'])
                ->get(['user_id', 'organisation_id']);

            foreach ($employees as $employee) {
                User::where('id', $employee->user_id)->update([
                    'employed_in_organisation_id' => $employee->organisation_id,
                ]);
            }

            $command->info('Done updating users.');
        }

        return 0;
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 May 2026 21:57:17 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp;

use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakUserUiProps implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public int $uniqueFor = 60;

    public function getJobUniqueId(User $user): string
    {
        return $user->id;
    }

    /**
     * Only flushes: GetFirstLoadProps rebuilds the props on the user's next page, under a key that
     * carries the deployment id. Rebuilding them here cost about 200 queries per profile save and
     * wrote them under a key without that id, which nothing ever read.
     */
    public function handle(User $user): void
    {
        setPermissionsTeamId($user->group_id);
        $user->unsetRelations();
        Cache::tags('grp-first-load-props:'.$user->id)->flush();
    }

    public function redoAllUsers(): void
    {
        foreach (User::where('status', true)->cursor() as $user) {
            BreakUserUiProps::dispatch($user);
        }
    }

    public function getCommandSignature(): string
    {
        return 'ui:recache-user-props {user?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('user')) {
            $user = User::where('slug', $command->argument('user'))->firstOrFail();
            $command->info('Recaching UI props for user: '.$user->slug);
            $this->handle($user);

            return 0;
        }

        /** @var User $user */
        foreach (User::where('status', true)->cursor() as $user) {
            $command->info('Recaching UI props for user: '.$user->slug);
            $this->handle($user);
        }


        return 0;
    }

}

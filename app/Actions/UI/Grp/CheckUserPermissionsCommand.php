<?php

namespace App\Actions\UI\Grp;

use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckUserPermissionsCommand
{
    use AsAction;

    public function handle(User $user, Command $command)
    {
        $roles = $user->roles()
            ->withoutGlobalScopes()
            ->get()
            ->sortBy([
                ['scope_type', 'asc'],
                ['scope_id', 'asc'],
            ])
            ->groupBy(['scope_type', 'scope_id']);

        foreach ($roles as $scopeType => $scopeIds) {
            foreach ($scopeIds as $scopeId => $groupRoles) {
                $roleNames = $groupRoles->pluck('name')->implode(', ');

                $command->info(
                    "Current Roles List [{$scopeType}: {$scopeId}] : {$roleNames}"
                );
            }
        }

        $authTo   = $command->option('authTo');
        if ($authTo) {
            $authTo = str_contains($authTo, ',') ? explode(',', $authTo) : [$authTo];
            $command->info("\n- Checking Permission -");

            foreach ($authTo as $auth) {
                $auth = trim($auth);
                $allowed = $user->hasPermissionTo($auth);
                $command->info("Permission [$auth]: $allowed");
            }
        }
    }

    public string $commandSignature = 'check:user-permission {username} {--authTo=}';

    public function asCommand(Command $command)
    {
        $username = $command->argument('username');

        if ($username) {
            $user = User::where('username', $username)->firstOrFail();
            $command->info("Checking roles & permission for user: [{$user->id}] {$user->username}");
            if ($user) {
                auth()->shouldUse('web');
                setPermissionsTeamId(group()->id);
                $this->handle($user, $command);
            } else {
                $command->info("- No user found -");
            }
        } else {
            $command->info("No username provided.");
        }
    }
}

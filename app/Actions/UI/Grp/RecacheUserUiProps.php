<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 May 2026 21:57:17 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

class RecacheUserUiProps implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(User $user): string
    {
        return $user->id;
    }

    public function handle(User $user, ?Command $command = null): void
    {
        setPermissionsTeamId($user->group_id);
        $cacheKeyPrefix = 'grp-first-load-props:'.$user->id.':';
        ClearCacheByWildcard::run($cacheKeyPrefix.'*', $command);

        $language = $user->language;
        $this->reCache($user, $language);

        if ($user->language->code != 'en') {
            $english = Language::where('code', 'en')->first();
            $this->reCache($user, $english);
        }
    }

    public function reCache(User $user, Language $language): void
    {
        $cacheKey = 'grp-first-load-props:'.$user->id.':'.$language->code;
        $ttl      = now()->addHours(24);

        $compute           = fn () => GetFirstLoadProps::make()->getUserUiProps($user, $language);
        $shouldCacheLayout = (bool)config('ui.cache.layout');

        try {
            $shouldCacheLayout
                ? Cache::remember($cacheKey, $ttl, $compute)
                : $compute();
        } catch (Throwable $e) {
            Sentry::captureException($e);
        }
    }

    public function redoAllUsers(): void
    {
        foreach (User::where('status', true)->cursor() as $user) {
            RecacheUserUiProps::dispatch($user);
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
            $this->handle($user, $command);

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

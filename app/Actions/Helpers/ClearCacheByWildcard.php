<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Feb 2025 17:16:43 Malaysia Time, Plane, KL-Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use Cache;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearCacheByWildcard
{
    use AsAction;


    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle(string $pattern, Command $command = null): void
    {

        $keys = GetRedisKeysByPattern::run($pattern, 'cache');
        $command?->line('Deleting  '.sprintf('%05d', count($keys)).'  cache keys matching pattern: '.$pattern);
        foreach ($keys as $key) {
            Cache::delete($key);
        }
    }

    public function getCommandSignature(): string
    {
        return 'cache:clear_by_wildcard {pattern}';
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function asCommand(Command $command): int
    {
        $this->handle($command->argument('pattern'), $command);

        return 0;
    }


}

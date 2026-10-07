<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteNotFoundPath;

use App\Models\Web\WebsiteNotFoundPath;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class PruneWebsiteNotFoundPaths
{
    use AsAction;

    public const int RETENTION_DAYS = 90;

    public function handle(): int
    {
        return WebsiteNotFoundPath::where('last_seen_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:prune_website_not_found_paths';
    }

    public function asCommand(Command $command): int
    {
        $command->line($this->handle().' not found paths deleted');

        return 0;
    }
}

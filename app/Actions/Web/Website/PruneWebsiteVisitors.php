<?php

namespace App\Actions\Web\Website;

use App\Models\Web\WebsiteVisitor;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class PruneWebsiteVisitors
{
    use AsAction;

    public const int RETENTION_DAYS = 30;

    public function handle(): void
    {
        WebsiteVisitor::where('first_seen_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:prune_website_visitors';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}

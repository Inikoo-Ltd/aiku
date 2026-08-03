<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 23:12:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\WebsiteHealthLog;

use App\Models\DevOps\WebsiteHealthLog;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class PruneWebsiteHealthLogs
{
    use AsAction;


    public function handle(int $days = 30, ?Command $command = null): void
    {
        $deleted = WebsiteHealthLog::where('created_at', '<', now()->subDays($days))->delete();

        if ($deleted > 0) {
            $command->info("Pruned $deleted old website health logs.");
        }
    }

    public function getCommandSignature(): string
    {
        return 'website-health-logs:prune {--days=30 : Number of days to keep logs}';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command->option('days'), $command);

        return 0;
    }

}

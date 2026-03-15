<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 15:22:20 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CleanProviderDispatchID
{
    use AsAction;

    public function handle(?Command $command = null): void
    {
        $deletedCount = DB::table('ses_dispatched_emails')
            ->where('send_at', '<', now()->subDays(60))
            ->delete();

        $command?->info("Deleted {$deletedCount} records from ses_dispatched_emails table.");
    }

    public function getCommandSignature(): string
    {
        return 'dispatched-email:clean-provider-dispatch-id';
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }

}

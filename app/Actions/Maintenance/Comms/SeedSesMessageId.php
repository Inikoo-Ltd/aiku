<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Mar 2026 01:05:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Comms;

use App\Models\Comms\DispatchedEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedSesMessageId
{
    use AsAction;


    protected function handle(DispatchedEmail $dispatchedEmail): void
    {
        $sesMessageId = $dispatchedEmail->provider_dispatch_id;
        if (!DB::table('ses_dispatched_emails')
            ->where('ses_id', $sesMessageId)->exists()) {
            DB::table('ses_dispatched_emails')->insert([
                'dispatched_email_id' => $dispatchedEmail->id,
                'ses_id'              => $sesMessageId,
                'send_at'             => $dispatchedEmail->sent_at
            ]);
        }
    }

    public string $commandSignature = 'repair:seed_ses_message_id';

    public function asCommand(Command $command): void
    {
        $count = DispatchedEmail::whereNotNull('sent_at')
            ->whereNotNull('provider_dispatch_id')
            ->where('sent_at', '>=', now()->subDays(61))
            ->count();

        $command->info("dispatched emails: $count");

        DispatchedEmail::whereNotNull('sent_at')
            ->whereNotNull('provider_dispatch_id')
            ->where('sent_at', '>=', now()->subDays(61))
            ->orderBy('sent_at', 'desc')
            ->chunk(
                1000,
                function ($dispatchedEmails) use ($command) {
                    foreach ($dispatchedEmails as $dispatchedEmail) {
                        $this->handle($dispatchedEmail);
                    }
                }
            );
    }

}

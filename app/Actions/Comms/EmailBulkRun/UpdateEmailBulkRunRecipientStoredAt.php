<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 25 Mar 2026 10:04:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailBulkRun;

use App\Models\Comms\EmailBulkRun;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEmailBulkRunRecipientStoredAt
{
    use AsAction;

    public function handle(EmailBulkRun $emailBulk): bool
    {
        $emailBulk->refresh();
        if (!$emailBulk->recipients_stored_at  && $emailBulk->recipients()->count() === $emailBulk->recipients_count) {
            UpdateEmailBulkRun::run(
                $emailBulk,
                [
                    'recipients_stored_at' => now()
                ]
            );

            return true;
        }

        return false;
    }
}

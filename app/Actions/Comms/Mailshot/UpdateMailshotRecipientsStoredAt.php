<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 13 Mar 2026 15:17:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMailshotRecipientsStoredAt
{
    use AsAction;

    public function handle(Mailshot $mailshot): bool
    {
        $mailshot->refresh();
        if (!$mailshot->recipients_stored_at && $mailshot->recipients_count !== null && $mailshot->recipients()->count() === $mailshot->recipients_count) {
            UpdateMailshot::run(
                $mailshot,
                [
                    'recipients_stored_at' => now()
                ]
            );

            return true;
        }

        return false;
    }
}

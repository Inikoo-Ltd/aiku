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

    public function handle(Mailshot $mailshot, $totalCustomer): bool
    {
        $mailshot->refresh();
        if (!$mailshot->recipients_stored_at) {

            // if all recipients are stored
            if ($mailshot->recipients()->count() === $totalCustomer) {
                UpdateMailshot::run(
                    $mailshot,
                    [
                        'recipients_stored_at' => now()
                    ]
                );
                return true;
            }
        }
        return false;
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Production's default mailer is not a real transport; staff emails go out through SES.
 */
trait SendsMailThroughSes
{
    protected function newMailMessage(): MailMessage
    {
        $message = new MailMessage();

        if (app()->isProduction()) {
            $message->mailer('ses')->from('help@aiku.io', 'Aiku');
        }

        return $message;
    }

    /**
     * Text a supplier typed shows as typed in the email, never as a link or formatting.
     */
    protected function plainLine(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_\[\]()<>#!|])/', '\\\\$1', $text);
    }
}

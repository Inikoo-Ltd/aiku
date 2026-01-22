<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailAddress;

use App\Actions\Comms\Ses\SendSesEmail;
use App\Models\Comms\Outbox;
use Lorisleiva\Actions\Concerns\AsAction;

// DELETE THIS!!! or fix it
class SendEmailAddress
{
    use AsAction;

    public mixed $message;

    public function handle(array $content, string $to, $attach = null, string $type = 'html'): void
    {
        $emailAddress = StoreEmailAddress::run(group(), $to);
        $response     = SendSesEmail::run($content, $emailAddress->email, $attach, $type);

        $modelData = [
            'ses_id'  => $response['MessageId'],
            'sent_at' => now()
        ];

        $outbox = Outbox::find(1); // TODO U need implement the real one, this just for test

    }

    public function attachments(array|string|null $attachments)
    {
        if (is_array($attachments)) {
            foreach ($attachments as $attach) {
                $this->message->attach($attach);
            }
        }

        if (is_string($attachments)) {
            $this->message->attach($attachments);
        }

        return $this->message;
    }
}

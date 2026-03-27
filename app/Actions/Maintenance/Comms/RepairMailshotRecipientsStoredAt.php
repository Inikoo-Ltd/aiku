<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 26 Mar 2026 14:35:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Actions\Comms\Mailshot\UpdateMailshot;
use App\Enums\Comms\MailshotSendChannel\MailshotSendChannelStateEnum;
use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMailshotRecipientsStoredAt
{
    use AsAction;

    public function handle(Mailshot $mailshot): array
    {
        if (!$mailshot->recipients_stored_at) {
            $countInProcess = $mailshot->channels()->whereNot('email_delivery_channels.state', MailshotSendChannelStateEnum::SENT)->count();

            if ($countInProcess === 0) {
                $sentAtDate = $mailshot->channels()->max('sent_at');
                $recipientsStoredAt = $sentAtDate ?: now();

                UpdateMailshot::run(
                    $mailshot,
                    [
                        'recipients_stored_at' => $recipientsStoredAt
                    ]
                );

                $msg = 'mailshot recipients stored has been updated to ' . $recipientsStoredAt;
            } else {
                return ['msg' => 'emails still processing'];
            }
        } else {
            $msg = 'mailshot recipients stored is already set';
        }

        Artisan::call('mailshot:sent-state', ['mailshot' => $mailshot->slug]);

        return ['msg' => $msg];
    }

    public string $commandSignature = 'repair:mailshot-recipients-stored-at {mailshot}';


    public function asCommand(Command $command): int
    {
        try {
            $mailshot = Mailshot::where('slug', $command->argument('mailshot'))->firstOrFail();
        } catch (Exception) {
            $command->error('Mailshot not found');

            return 1;
        }
        $res = $this->handle($mailshot);

        $command->line(Arr::get($res, 'msg', ''));

        return 0;
    }
}

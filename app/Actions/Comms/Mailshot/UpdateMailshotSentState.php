<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\MailshotSendChannel\MailshotSendChannelStateEnum;
use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMailshotSentState
{
    use AsAction;

    public function handle(Mailshot $mailshot): array
    {
        if (!$mailshot->recipients_stored_at) {
            return [
                'msg' => 'emails still processing'
            ];
        }
        $count = $mailshot->channels()->count();
        if ($count == 0) {
            return [
                'error' => true,
                'msg'  => 'no channels found'
            ];
        }

        $countInProcess = $mailshot->channels()->whereNot('email_delivery_channels.state', MailshotSendChannelStateEnum::SENT)->count();

        if ($countInProcess > 0) {
            return [
                'error' => true,
                'msg'  => 'Channels still processing '.$countInProcess
            ];
        }

        $sentAtDate = $mailshot->channels()->max('sent_at');


        UpdateMailshot::run(
            $mailshot,
            [
            'state'  => MailshotStateEnum::SENT,
            'sent_at' => $sentAtDate
        ]
        );
        return [
            'msg' => 'mailshot sent'
        ];
    }

    public string $commandSignature = 'mailshot:sent-state {mailshot}';


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

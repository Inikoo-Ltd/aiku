<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailBulkRun;

use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Models\Comms\EmailBulkRun;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEmailBulkRunSentState
{
    use AsAction;

    public function handle(EmailBulkRun $emailBulkRun): array
    {
        if (!$emailBulkRun->recipients_stored_at) {
            return [
                'msg' => 'emails still processing'
            ];
        }
        $count = $emailBulkRun->channels()->count();
        if ($count == 0) {
            return [
                'error' => true,
                'msg'  => 'no channels found'
            ];
        }

        $countInProcess = $emailBulkRun->channels()->whereNot('email_delivery_channels.state', EmailDeliveryChannelStateEnum::SENT)->count();

        if ($countInProcess > 0) {
            return [
                'error' => true,
                'msg'  => 'Channels still processing '.$countInProcess
            ];
        }

        $sentAtDate = $emailBulkRun->channels()->max('sent_at');


        UpdateEmailBulkRun::run(
            $emailBulkRun,
            [
            'state'  => EmailBulkRunStateEnum::SENT,
            'sent_at' => $sentAtDate
        ]
        );
        return [
            'msg' => 'bulk run sent'
        ];
    }

    public string $commandSignature = 'email_bulk_run:sent_state {email_bulk_run}';


    public function asCommand(Command $command): int
    {
        try {
            $emailBulkRun = EmailBulkRun::where('slug', $command->argument('email_bulk_run'))->firstOrFail();
        } catch (Exception) {
            $command->error('Email bulk run not found');

            return 1;
        }
        $res = $this->handle($emailBulkRun);

        $command->line(Arr::get($res, 'msg', ''));

        return 0;
    }

}

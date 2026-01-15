<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailDeliveryChannel;

use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateCumulativeDispatchedEmails;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunSentState;
use App\Actions\Comms\Mailshot\GetHtmlLayout;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\Comms\Mailshot\UpdateMailshotSentState;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailDeliveryChannel;
use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SendEmailDeliveryChannel
{
    use AsAction;
    use WithSendBulkEmails;

    public string $jobQueue = 'ses';

    public function handle(EmailDeliveryChannel $emailDeliveryChannel): void
    {
        /** @var Mailshot|EmailBulkRun $model */
        $model         = $emailDeliveryChannel->model;
        $emailHtmlBody = GetHtmlLayout::run($model);

        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'start_sending_at' => now(),
                'state'            => EmailDeliveryChannelStateEnum::SENDING
            ]
        );


        foreach ($model->recipients()->where('channel', $emailDeliveryChannel->id)->get() as $recipient) {
            $model->refresh();


            if ($this->isModelStopped($model)) {
                UpdateEmailDeliveryChannel::run(
                    $emailDeliveryChannel,
                    [
                        'state' => EmailDeliveryChannelStateEnum::STOPPED
                    ]
                );

                return;
            }

            //todo this is wrong
            $unsubscribeUrl = route('org.unsubscribe.mailshot.show', $recipient->dispatchedEmail->ulid);

            $this->sendEmailWithMergeTags(
                $recipient->dispatchedEmail,
                $model->sender(),
                $model->subject,
                $emailHtmlBody,
                $unsubscribeUrl,
            );
        }


        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'sent_at' => now(),
                'state'   => EmailDeliveryChannelStateEnum::SENT
            ]
        );
        $model->refresh();

        if ($model instanceof Mailshot) {
            MailshotHydrateDispatchedEmails::run($model);
            UpdateMailshotSentState::run($model);
        } else {
            EmailBulkRunHydrateCumulativeDispatchedEmails::run($model, DispatchedEmailStateEnum::SENT);
            EmailBulkRunHydrateDispatchedEmails::run($model);
            UpdateEmailBulkRunSentState::run($model);
        }
    }

    private function isModelStopped(Mailshot|EmailBulkRun $model): bool
    {
        if ($model instanceof Mailshot) {
            $stopped = $model->state == MailshotStateEnum::STOPPED;
        } else {
            $stopped = $model->state == EmailBulkRunStateEnum::STOPPED;
        }

        return $stopped;
    }


    public string $commandSignature = 'mailshot:send-channel {mailshot} {?channel}';


    public function asCommand(Command $command): int
    {
        try {
            $mailshot = Mailshot::where('slug', $command->argument('mailshot'))->firstOrFail();
        } catch (Exception) {
            $command->error('Mailshot not found');

            return 1;
        }

        $chanelQuery = $mailshot->channels();
        if ($command->argument('channel')) {
            $chanelQuery->where('channel.id', $command->argument('channel'));
        }

        foreach ($chanelQuery->get() as $channel) {
            $this->handle($channel);
        }


        return 0;
    }

}

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
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailBulkRunRecipient;
use App\Models\Comms\EmailDeliveryChannel;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotRecipient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SendEmailDeliveryChannel
{
    use AsAction;
    use WithSendBulkEmails;

    public string $jobQueue = 'ses-send';

    public function handle(EmailDeliveryChannel $emailDeliveryChannel, bool $debug = false): void
    {

        if(!$debug) {
            if ($emailDeliveryChannel->state != EmailDeliveryChannelStateEnum::READY) {
                return;
            }
        }

        if ($debug) {
            print "Start sending delivery channel  to $emailDeliveryChannel->id ".now()->toDateTimeString()."\n";
        }

        /** @var Mailshot|EmailBulkRun $model */
        $model = $emailDeliveryChannel->model;
        $emailHtmlBody = GetHtmlLayout::run($model);

        if ($emailDeliveryChannel->state == EmailDeliveryChannelStateEnum::READY) {
            UpdateEmailDeliveryChannel::run(
                $emailDeliveryChannel,
                [
                    'start_sending_at' => now(),
                    'state'            => EmailDeliveryChannelStateEnum::SENDING
                ]
            );
        }

        if ($debug) {
            print "Start sending delivery channel XX to $emailDeliveryChannel->id ".now()->toDateTimeString()."\n";
        }
        /** @var EmailBulkRunRecipient|MailshotRecipient $recipient */
        foreach ($model->recipients()->where('channel', $emailDeliveryChannel->id)->get() as $recipient) {
            /** @var DispatchedEmail $dispatchedEmail */
            $dispatchedEmail = $recipient->dispatchedEmail;

            if ($dispatchedEmail->state != DispatchedEmailStateEnum::READY) {
                continue;
            }

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

            if ($debug) {
                print "Start DE   to $dispatchedEmail->id ".now()->toDateTimeString()."\n";
            }

            $encryptedDispatchedEmailID = Crypt::encryptString($dispatchedEmail->id);


            // Send redirect URL
            $unsubscribeUrl = route('grp.redirect_unsubscribe', $encryptedDispatchedEmailID);

            $subject = ($model instanceof EmailBulkRun) ? $model->outbox->emailOngoingRun->email->subject : $model->subject;

            if ($debug) {
                print "Start DE (AA)  to $dispatchedEmail->id ".now()->toDateTimeString()."\n";
            }

            $additionalData = $dispatchedEmail->data['additional_data'] ?? [];

            if($recipient->recipient_type=='Customer'){
                $recipientData=DB::table('customers')->select('name')->where('id',$recipient->recipient_id)->first();
                if($recipientData){
                    $additionalData['customer_name']=$recipientData->name;
                }

            }

            if ($debug) {
                print "------------>   ".Arr::get($additionalData,'customer_name')."\n";
            }

            $this->sendEmailWithMergeTags(
                $dispatchedEmail,
                $model->sender(),
                $subject,
                $emailHtmlBody,
                $unsubscribeUrl,
                additionalData: $additionalData,
                senderName: $model->senderName(),
                debug: $debug
            );

            if ($debug) {
                print "Finish  DE (AA)  to $dispatchedEmail->id ".now()->toDateTimeString()."\n";
            }
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
            MailshotHydrateDispatchedEmails::dispatch($model->id)->delay(now()->addSeconds());
            UpdateMailshotSentState::run($model);
        } elseif ($model instanceof EmailBulkRun) {
            EmailBulkRunHydrateCumulativeDispatchedEmails::run($model, DispatchedEmailStateEnum::SENT);
            EmailBulkRunHydrateDispatchedEmails::dispatch($model->id);
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


    public string $commandSignature = 'mailshot:send-channel {channel}';


    public function asCommand(Command $command): int
    {
        $emailDeliveryChannel = EmailDeliveryChannel::findOrFail($command->argument('channel'));
        $this->handle($emailDeliveryChannel, true);


        return 0;
    }
}

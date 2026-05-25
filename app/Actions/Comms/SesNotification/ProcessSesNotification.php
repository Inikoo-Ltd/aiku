<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\SesNotification;

use App\Actions\Comms\EmailTrackingEvent\PostProcessingEmailTrackingEvent;
use App\Actions\Comms\EmailTrackingEvent\StoreEmailTrackingEvent;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateDispatchedEmails;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Utils\IsGoogleIp;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\SesNotification;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSesNotification
{
    use AsAction;
    use WithActionUpdate;

    public string $jobQueue = 'ses-analytics';

    public function handle(SesNotification $sesNotification): ?array
    {
        $dispatchedEmail = $this->getDispatchedEmail($sesNotification->message_id);
        if (!$dispatchedEmail) {
            $sesNotification->delete();

            return [];
        }

        $dispatchedEmailState = null;
        $additionalData       = [];
        $data                 = [];

        switch (Arr::get($sesNotification->data, 'eventType')) {
            case 'Send':
                $date = Carbon::parse(Arr::get($sesNotification->data, 'mail.timestamp'));
                $type = EmailTrackingEventTypeEnum::SENT;

                if (in_array($dispatchedEmail->state, [
                    DispatchedEmailStateEnum::READY,
                    DispatchedEmailStateEnum::ERROR,
                    DispatchedEmailStateEnum::REJECTED_BY_PROVIDER,
                ])) {
                    $dispatchedEmailState = DispatchedEmailStateEnum::SENT;
                }

                break;
            case 'Bounce':

                $data = [
                    'v' => 1,
                    't' => Arr::get($sesNotification->data, 'bounce.bounceSubType'),
                ];
                foreach (Arr::get($sesNotification->data, 'bounce.bouncedRecipients', []) as $bouncedRecipients) {
                    data_set($data, 'a', Arr::get($bouncedRecipients, 'action'));
                    data_set($data, 'c', Arr::get($bouncedRecipients, 'status'));
                    $details = Arr::get($bouncedRecipients, 'diagnosticCode');
                    $details = str_replace("\n", PHP_EOL, $details ?? '');
                    data_set($data, 'd', $details);
                }

                $isHardBounce = Arr::get($sesNotification->data, 'bounce.bounceType') == 'Permanent';
                $date         = Carbon::parse(Arr::get($sesNotification->data, 'bounce.timestamp'));

                $type = EmailTrackingEventTypeEnum::SOFT_BOUNCE;
                if ($isHardBounce) {
                    $type = EmailTrackingEventTypeEnum::HARD_BOUNCE;
                }

                if (!in_array($dispatchedEmail->state, [
                    DispatchedEmailStateEnum::OPENED,
                    DispatchedEmailStateEnum::CLICKED,
                    DispatchedEmailStateEnum::SPAM,
                    DispatchedEmailStateEnum::UNSUBSCRIBED,
                ])) {
                    $dispatchedEmailState = DispatchedEmailStateEnum::SOFT_BOUNCE;
                    if ($isHardBounce) {
                        $dispatchedEmailState = DispatchedEmailStateEnum::HARD_BOUNCE;
                    }
                }


                break;
            case 'Complaint':

                $date = Carbon::parse(Arr::get($sesNotification->data, 'complaint.timestamp'));
                $type = EmailTrackingEventTypeEnum::MARKED_AS_SPAM;

                $dispatchedEmailState = DispatchedEmailStateEnum::SPAM;
                $data                 = [
                    'v' => 1,
                ];

                if (Arr::has($sesNotification->data, 'complaint.complaintSubType')) {
                    $data['t'] = Arr::get($sesNotification->data, 'complaint.complaintSubType');
                }

                if (Arr::has($sesNotification->data, 'complaint.complaintFeedbackType')) {
                    $data['f'] = Arr::get($sesNotification->data, 'complaint.complaintFeedbackType');
                }


                $additionalData = [
                    'mask_as_spam' => true
                ];

                break;


            case 'Delivery':
                $date = Carbon::parse(Arr::get($sesNotification->data, 'delivery.timestamp'));
                $type = EmailTrackingEventTypeEnum::DELIVERED;

                if (in_array($dispatchedEmail->state, [
                    DispatchedEmailStateEnum::READY,
                    DispatchedEmailStateEnum::SENT,
                    DispatchedEmailStateEnum::ERROR,
                    DispatchedEmailStateEnum::REJECTED_BY_PROVIDER,
                    DispatchedEmailStateEnum::DELAY,
                ])) {
                    $dispatchedEmailState = DispatchedEmailStateEnum::DELIVERED;
                }

                break;
            case 'Reject':
                $date = Carbon::parse(Arr::get($sesNotification->data, 'mail.timestamp', now()));

                $type                 = EmailTrackingEventTypeEnum::DECLINED_BY_PROVIDER;
                $dispatchedEmailState = DispatchedEmailStateEnum::REJECTED_BY_PROVIDER;

                break;
            case 'Open':

                $date = Carbon::parse(Arr::get($sesNotification->data, 'open.timestamp'));
                $type = EmailTrackingEventTypeEnum::OPENED;

                if (!in_array($dispatchedEmail->state, [
                    DispatchedEmailStateEnum::CLICKED,
                    DispatchedEmailStateEnum::UNSUBSCRIBED,
                    DispatchedEmailStateEnum::SPAM,
                ])) {
                    $dispatchedEmailState = DispatchedEmailStateEnum::OPENED;
                }

                $data = Arr::only($sesNotification->data['open'], ['ipAddress', 'userAgent']);

                if (Arr::get($data, 'userAgent') == "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246 Mozilla/5.0"
                    && IsGoogleIp::run(Arr::get($data, 'ipAddress'))
                ) {
                    $sesNotification->delete();

                    return null;
                }

                $additionalData = [
                    'last_read_at' => $date
                ];

                if (!$dispatchedEmail->first_read_at) {
                    $additionalData = [
                        'first_read_at' => $date,
                        'last_read_at'  => $date
                    ];
                }

                break;
            case 'Click':
                $date = Carbon::parse(Arr::get($sesNotification->data, 'click.timestamp'));

                $type = EmailTrackingEventTypeEnum::CLICKED;

                if ($dispatchedEmail->state != DispatchedEmailStateEnum::UNSUBSCRIBED) {
                    $dispatchedEmailState = DispatchedEmailStateEnum::CLICKED;
                }

                $data = [
                    'v'         => 1,
                    'l'         => Arr::get($sesNotification->data, 'click.link'),
                    'userAgent' => Arr::get($sesNotification->data, 'click.userAgent'),
                    'ipAddress' => Arr::get($sesNotification->data, 'click.ipAddress'),
                ];

                if (Arr::get($sesNotification->data, 'click.linkTags')) {
                    $data['t'] = Arr::get($sesNotification->data, 'click.linkTags');
                }


                $additionalData = [
                    'last_clicked_at' => $date
                ];


                if (!$dispatchedEmail->first_clicked_at) {
                    $additionalData = [
                        'first_clicked_at' => $date,
                        'last_clicked_at'  => $date
                    ];
                }

                break;
            case 'DeliveryDelay':
                $date = Carbon::parse(Arr::get($sesNotification->data, 'deliveryDelay.timestamp'));

                $type = EmailTrackingEventTypeEnum::DELAY;

                if (in_array($dispatchedEmail->state, [
                    DispatchedEmailStateEnum::READY,
                    DispatchedEmailStateEnum::SENT,
                    DispatchedEmailStateEnum::ERROR,
                    DispatchedEmailStateEnum::REJECTED_BY_PROVIDER,
                ])) {
                    $dispatchedEmailState = DispatchedEmailStateEnum::DELAY;
                }

                $data = [
                    't' => Arr::get($sesNotification->data, 'deliveryDelay.delayType'),
                ];


                break;

            default:
                return $sesNotification->data;
        }


        $emailProcessingTrackingEvent = StoreEmailTrackingEvent::make()->action($dispatchedEmail, [
            'type'       => $type,
            'data'       => $data,
            'created_at' => $date
        ]);


        if ($dispatchedEmailState !== null || !empty($additionalData)) {
            $dataToUpdate = [];
            if ($dispatchedEmailState !== null) {
                $dataToUpdate['state'] = $dispatchedEmailState;
            }
            if (!empty($additionalData)) {
                $dataToUpdate['data'] = array_merge($dispatchedEmail->data ?? [], $additionalData);
            }

            if (!empty($dataToUpdate)) {
                $this->update($dispatchedEmail, $dataToUpdate);
            }
        }

        $sesNotification->delete();

        PostProcessingEmailTrackingEvent::dispatch($emailProcessingTrackingEvent->id)->delay(1);
        OutboxHydrateDispatchedEmails::dispatch($dispatchedEmail->outbox_id)->delay(120);

        return null;
    }

    public function getDispatchedEmail(string $sesMessageID): ?DispatchedEmail
    {
        $dispatchedEmailData = DB::connection('aiku_no_sticky')->table('ses_dispatched_emails')->select('dispatched_email_id')->where('ses_id', $sesMessageID)->first();
        if ($dispatchedEmailData) {
            $dispatchedEmail = DispatchedEmail::on('aiku_no_sticky')->find($dispatchedEmailData->dispatched_email_id);
            if ($dispatchedEmail) {
                return $dispatchedEmail;
            }
        }

        return null;
    }

    public string $commandSignature = 'ses-notify:process {id?}';


    public function asCommand(Command $command): int
    {
        if ($command->argument('id')) {
            try {
                $sesNotification = SesNotification::find($command->argument('id'));


                $command->line($sesNotification->message_id);
                $this->handle($sesNotification);

                return 0;
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        }
        $command->line('Number ses notifications to process '.SesNotification::count());


        return 0;
    }


}

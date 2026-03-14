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
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSesNotification
{
    use AsAction;
    use WithActionUpdate;

    public string $jobQueue = 'low-priority';

    public function handle(SesNotification $sesNotification): ?array
    {
        $dispatchedEmail = $this->getDispatchedEmail($sesNotification->message_id);

        if (!$dispatchedEmail) {
            $sesNotification->delete();

            return [];
        }

        $additionalData = [];

        switch (Arr::get($sesNotification->data, 'eventType')) {
            case 'Bounce':
                $data         = Arr::only($sesNotification->data['bounce'], ['bounceType', 'bounceSubType', 'reportingMTA']);
                $isHardBounce = Arr::get($sesNotification->data, 'bounce.bounceType') == 'Permanent';

                $type                 = EmailTrackingEventTypeEnum::SOFT_BOUNCE;
                $dispatchedEmailState = DispatchedEmailStateEnum::SOFT_BOUNCE;

                if ($isHardBounce) {
                    $type                 = EmailTrackingEventTypeEnum::HARD_BOUNCE;
                    $dispatchedEmailState = DispatchedEmailStateEnum::HARD_BOUNCE;
                }

                break;
            case 'Complaint':
                $type                 = EmailTrackingEventTypeEnum::MARKED_AS_SPAM;
                $dispatchedEmailState = DispatchedEmailStateEnum::SPAM;
                $data                 = Arr::only($sesNotification->data['complaint'], ['userAgent', 'complaintSubType', 'complaintFeedbackType']);

                $additionalData = [
                    'mask_as_spam' => true
                ];

                break;
            case 'Delivery':
                $type                 = EmailTrackingEventTypeEnum::DELIVERED;
                $dispatchedEmailState = DispatchedEmailStateEnum::DELIVERED;
                $data                 = Arr::only($sesNotification->data['delivery'], ['remoteMtaIp', 'smtpResponse']);

                break;
            case 'Reject':
                $type                 = EmailTrackingEventTypeEnum::DECLINED_BY_PROVIDER;
                $dispatchedEmailState = DispatchedEmailStateEnum::REJECTED_BY_PROVIDER;
                $data                 = Arr::only($sesNotification->data['reject'], ['ipAddress', 'userAgent']);

                break;

            case 'Open':
                $type                 = EmailTrackingEventTypeEnum::OPENED;
                $dispatchedEmailState = DispatchedEmailStateEnum::OPENED;
                $data                 = Arr::only($sesNotification->data['open'], ['ipAddress', 'userAgent']);

                if (Arr::get($data, 'userAgent') == "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246 Mozilla/5.0"
                    && IsGoogleIp::run(Arr::get($data, 'ipAddress'))
                ) {
                    $sesNotification->delete();

                    return null;
                }

                $additionalData = [
                    'last_read_at' => now()
                ];

                if (!$dispatchedEmail->first_read_at) {
                    $additionalData = [
                        'first_read_at' => now(),
                        'last_read_at'  => now()
                    ];
                }

                break;
            case 'Click':
                $type                 = EmailTrackingEventTypeEnum::CLICKED;
                $dispatchedEmailState = DispatchedEmailStateEnum::CLICKED;
                $data                 = Arr::only($sesNotification->data['click'], ['ipAddress', 'userAgent']);

                $additionalData = [
                    'last_clicked_at' => now()
                ];

                if (!$dispatchedEmail->first_clicked_at) {
                    $additionalData = [
                        'first_clicked_at' => now(),
                        'last_clicked_at'  => now()
                    ];
                }

                break;

            case 'DeliveryDelay':
                $type                 = EmailTrackingEventTypeEnum::DECLINED_BY_PROVIDER;
                $dispatchedEmailState = DispatchedEmailStateEnum::DELAY;
                $data                 = Arr::only($sesNotification->data['deliveryDelay'], ['ipAddress', 'userAgent']);

                break;

            default:
                return $sesNotification->data;
        }

        $emailProcessingTrackingEvent = StoreEmailTrackingEvent::make()->action($dispatchedEmail, [
            'type' => $type,
            'data' => $data
        ]);

        $this->update($dispatchedEmail, [
            'state' => $dispatchedEmailState,
            ...$additionalData
        ]);

        $sesNotification->delete();

        PostProcessingEmailTrackingEvent::dispatch($emailProcessingTrackingEvent);
        OutboxHydrateDispatchedEmails::dispatch($dispatchedEmail->outbox_id)->delay(5);

        return null;
    }

    public function getDispatchedEmail(string $sesMessageID): ?DispatchedEmail
    {
        $dispatchedEmailData = DB::table('ses_dispatched_emails')->select('dispatched_email_id')->where('ses_id', $sesMessageID)->first();
        if (!$dispatchedEmailData) {
            $dispatchedEmail = DispatchedEmail::find($dispatchedEmailData->dispatched_email_id);
            if ($dispatchedEmail) {
                return $dispatchedEmail;
            }
        }
        // return null; //todo uncomment this
        return DispatchedEmail::where('provider_dispatch_id', $sesMessageID)->first();// todo remove this
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

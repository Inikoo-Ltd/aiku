<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\SesNotification;

use App\Actions\Comms\DispatchedEmail\UpdateDispatchedEmail;
use App\Actions\Comms\EmailTrackingEvent\StoreEmailTrackingEvent;
use App\Actions\CRM\Prospect\UpdateProspectEmailClicked;
use App\Actions\CRM\Prospect\UpdateProspectEmailHardBounced;
use App\Actions\CRM\Prospect\UpdateProspectEmailOpened;
use App\Actions\CRM\Prospect\UpdateProspectEmailSoftBounced;
use App\Actions\CRM\Prospect\UpdateProspectEmailUnsubscribed;
use App\Actions\Utils\IsGoogleIp;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\DispatchedEmailEvent\DispatchedEmailEventTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\SesNotification;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSesNotification
{
    use AsAction;

    public function handle(SesNotification $sesNotification): ?array
    {
        $dispatchedEmail = DispatchedEmail::where('provider_message_id', $sesNotification->message_id)->first();

        if (!$dispatchedEmail) {
            $sesNotification->delete();

            return [];
        }
        switch (Arr::get($sesNotification->data, 'eventType')) {
            case 'Bounce':
                $type = DispatchedEmailEventTypeEnum::BOUNCE;
                $date = Arr::get($sesNotification->data, 'bounce.timestamp');
                $data = Arr::only($sesNotification->data['bounce'], ['bounceType', 'bounceSubType', 'reportingMTA']);

                $isHardBounce = Arr::get($sesNotification->data, 'bounce.bounceType') == 'Permanent';

                if ($dispatchedEmail->recipient_type == 'Prospect') {
                    if ($isHardBounce) {
                        UpdateProspectEmailHardBounced::run(
                            $dispatchedEmail->recipient,
                            new Carbon($date)
                        );
                    } else {
                        UpdateProspectEmailSoftBounced::run(
                            $dispatchedEmail->recipient,
                            new Carbon($date)
                        );
                    }
                }


                if ($isHardBounce) {
                    $dispatchedEmail->email()->update(
                        [
                            'is_hard_bounced'  => true,
                            'hard_bounce_type' => Arr::get($sesNotification->data, 'bounce.bounceSubType')
                        ]
                    );
                }

                UpdateDispatchedEmail::run(
                    $dispatchedEmail,
                    [
                        'state'           => $isHardBounce ? DispatchedEmailStateEnum::HARD_BOUNCE : DispatchedEmailStateEnum::SOFT_BOUNCE,
                        'date'            => $date,
                        'is_hard_bounced' => $isHardBounce,
                        'is_soft_bounced' => !$isHardBounce

                    ]
                );

                break;
            case 'Complaint':
                $type = DispatchedEmailEventTypeEnum::COMPLAIN;
                $date = Arr::get($sesNotification->data, 'complaint.timestamp');
                $data = Arr::only($sesNotification->data['complaint'], ['userAgent', 'complaintSubType', 'complaintFeedbackType']);

                if ($dispatchedEmail->recipient_type == 'Prospect') {
                    UpdateProspectEmailUnsubscribed::run($dispatchedEmail->recipient, new Carbon($date));
                }

                UpdateDispatchedEmail::run(
                    $dispatchedEmail,
                    [
                        'state'   => DispatchedEmailStateEnum::SPAM,
                        'is_spam' => true,
                        'date'    => $date,
                    ]
                );
                break;
            case 'Delivery':
                $type = DispatchedEmailEventTypeEnum::DELIVERY;
                $date = Arr::get($sesNotification->data, 'delivery.timestamp');
                $data = Arr::only($sesNotification->data['delivery'], ['remoteMtaIp', 'smtpResponse']);

                UpdateDispatchedEmail::run(
                    $dispatchedEmail,
                    [
                        'state'        => DispatchedEmailStateEnum::DELIVERED,
                        'date'         => $date,
                        'delivered_at' => $date,
                        'is_delivered' => true
                    ]
                );
                break;
            case 'Reject':

                if ($dispatchedEmail->state == DispatchedEmailStateEnum::READY) {
                    UpdateDispatchedEmail::run(
                        $dispatchedEmail,
                        [
                            'state'       => DispatchedEmailStateEnum::REJECTED,
                            'is_rejected' => true,
                        ]
                    );
                }
                $sesNotification->delete();

                return null;


            case 'Open':
                $type = DispatchedEmailEventTypeEnum::OPEN;
                $date = Arr::get($sesNotification->data, 'open.timestamp');
                $data = Arr::only($sesNotification->data['open'], ['ipAddress', 'userAgent']);



                if (Arr::get($data, 'userAgent') == "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246 Mozilla/5.0"
                    and IsGoogleIp::run(Arr::get($data, 'ipAddress'))
                ) {
                    $sesNotification->delete();
                    return null;
                }


                if ($dispatchedEmail->recipient_type == 'Prospect') {
                    UpdateProspectEmailOpened::run($dispatchedEmail->recipient, new Carbon($date));
                }

                UpdateDispatchedEmail::run(
                    $dispatchedEmail,
                    [
                        'state'     => DispatchedEmailStateEnum::OPENED,
                        'date'      => $date,
                        'is_opened' => true
                    ]
                );

                break;
            case 'Click':
                StoreEmailTrackingEvent::make()->action($dispatchedEmail, [
                    'provider_reference' => $sesNotification->message_id
                ]);
                /*       $type = DispatchedEmailEventTypeEnum::CLICK;
                       $date = Arr::get($sesNotification->data, 'click.timestamp');
                       $data = Arr::only($sesNotification->data['click'], ['ipAddress', 'userAgent', 'link', 'linkTags']);

                       if ($dispatchedEmail->recipient_type == 'Prospect') {
                           UpdateProspectEmailClicked::run($dispatchedEmail->recipient, new Carbon($date));
                       }*/

                // Create storetracking event and call hydrators

                /*         UpdateDispatchedEmail::run(
                             $dispatchedEmail,
                             [
                                 'state'      => DispatchedEmailStateEnum::CLICKED,
                                 'date'       => $date,
                                 'is_clicked' => true
                             ]
                         );*/

                break;

            case 'DeliveryDelay':
                $type = DispatchedEmailEventTypeEnum::DELIVERY_DELAY;
                $date = Arr::get($sesNotification->data, 'deliveryDelay.timestamp');
                $data = Arr::only(
                    $sesNotification->data['deliveryDelay'],
                    ['delayType', 'expirationTime', 'reportingMTA']
                );

                break;

            default:
                return $sesNotification->data;
        }

        $sesNotification->delete();


        $eventData = [
            'type' => $type,
            'date' => $date,
            'data' => $data
        ];


        $dispatchedEmail->events()->create($eventData);


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

        foreach (SesNotification::all() as $sesNotification) {
            $pending = $this->handle($sesNotification);
            if ($pending) {
                // print_r($pending);
            }
        }

        return 0;
    }


}

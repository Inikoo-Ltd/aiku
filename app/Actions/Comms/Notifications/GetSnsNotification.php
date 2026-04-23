<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Notifications;

use App\Actions\Comms\SesNotification\ProcessSesNotification;
use App\Models\Comms\SesNotification;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Psr\Http\Message\ServerRequestInterface;

class GetSnsNotification
{
    use AsAction;

    public function asController(ServerRequestInterface $request): string
    {
        $message   = Message::fromPsrRequest($request);
        $validator = new MessageValidator();
        if ($validator->isValid($message)) {
            if ($message['Type'] == 'SubscriptionConfirmation') {
                file_get_contents($message['SubscribeURL']);
            } elseif ($message['Type'] === 'Notification') {
                $messageData = json_decode($message['Message'], true);

                $type = Arr::get($messageData, 'notificationType');
                if ($type == 'notificationType') {
                    return 'ok';
                }

                if ($messageId = Arr::get($messageData, 'mail.messageId')) {

                    $eventType = Arr::get($messageData, 'eventType', 'unknown');

                    $sesNotification = SesNotification::create(
                        [
                            'message_id' => $messageId,
                            'data'       => $messageData,
                            'event_type' => $eventType
                        ]
                    );
                    if($eventType == 'Send'){
                        ProcessSesNotification::dispatch($sesNotification);
                    }else{
                        ProcessSesNotification::dispatch($sesNotification)->delay(now()->addMinutes(60));

                    }
                }
            }
        }

        return 'ok';
    }


}

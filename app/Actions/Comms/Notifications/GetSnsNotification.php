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
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSnsNotification
{
    use AsAction;

    public function asController(ActionRequest $request): string
    {
        $message   = new Message($request->all());
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

                    $sesNotification = SesNotification::create(
                        [
                            'message_id' => $messageId,
                            'data'      => $messageData
                        ]
                    );

                    // ProcessSesNotification::dispatch($sesNotification);

                }
            }
        }
        return 'ok';
    }



}

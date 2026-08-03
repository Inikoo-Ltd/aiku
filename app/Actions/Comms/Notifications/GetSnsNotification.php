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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Psr\Http\Message\ServerRequestInterface;

class GetSnsNotification
{
    use AsAction;

    public function asController(ServerRequestInterface $request): string
    {
        $message   = Message::fromPsrRequest($request);
        $validator = new MessageValidator(function ($certUrl) {
            $cacheKey = 'sns-cert:'.md5($certUrl);
            if ($cert = Cache::get($cacheKey)) {
                return $cert;
            }

            $response = Http::get($certUrl);
            if ($response->successful()) {
                $cert = $response->body();
                Cache::put($cacheKey, $cert, now()->addWeek());

                return $cert;
            }

            return false;
        });
        if ($validator->isValid($message)) {
            if ($message['Type'] == 'SubscriptionConfirmation') {
                Http::get($message['SubscribeURL']);
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
                    ProcessSesNotification::dispatch($sesNotification)->delay(2);
                }
            }
        }

        return 'ok';
    }


}

<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 13 Feb 2026 09:06:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Actions\Comms\Traits\WithSendExternalOutboxEmail;
use App\Models\Catalogue\Shop;
use App\Models\Comms\ExternalEmailRecipient;
use App\Models\Comms\Outbox;

class SendChatNotificationToExternal extends OrgAction
{
    use WithSendExternalOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(ExternalEmailRecipient $externalEmailRecipient, Shop $shop, array $additionalData = []): DispatchedEmail|null
    {
        /** @var Outbox $outbox */
        $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER->value)->first();

        return $this->sendOutboxEmailToExternal($externalEmailRecipient, $outbox, $additionalData);
    }
}

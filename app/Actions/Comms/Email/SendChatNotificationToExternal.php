<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 13 Feb 2026 09:06:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendChatOutboxEmail;
use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\ChatEmailRecipient;
use App\Models\Comms\DispatchedEmail;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;

class SendChatNotificationToExternal extends OrgAction
{
    use WithSendChatOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(ChatEmailRecipient $chatEmailRecipient, Shop $shop, array $additionalData = []): DispatchedEmail|null
    {
        /** @var Outbox $outbox */
        $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER->value)->first();

        if (!$outbox) {
            return null;
        }

        if ($outbox->state != OutboxStateEnum::ACTIVE) {
            return null;
        }

        return $this->sendOutboxEmailToChatRecipient($chatEmailRecipient, $outbox, $additionalData);
    }
}

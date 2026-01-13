<?php

/*
 * author eka yudinata
 * created on 30-12-2025
 * github: https://github.com/ekayudinata
 * copyright 2025
*/

namespace App\Actions\Comms\Email;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\CRM\Customer;
use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;

class SendCreditBalanceEmailToUser extends OrgAction
{
    use WithSendSubscribersOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(Customer $customer, array $additionalData = []): void
    {
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::CREDIT_BALANCE_NOTIFICATION_FOR_USER)->first();
        $this->sendOutboxEmailToSubscribers($outbox, $additionalData);
    }
}

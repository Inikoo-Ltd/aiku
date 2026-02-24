<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 10 Feb 2026 10:53:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;

class SendChatNotificationToCustomer extends OrgAction
{
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(Customer $customer, array $additionalData = []): DispatchedEmail|null
    {
        return $this->sendCustomerOutboxEmail($customer, OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER, $additionalData);
    }
}

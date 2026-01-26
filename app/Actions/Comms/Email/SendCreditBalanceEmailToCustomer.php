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
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;

class SendCreditBalanceEmailToCustomer extends OrgAction
{
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(Customer $customer, array $additionalData = []): DispatchedEmail|null
    {
        return $this->sendCustomerOutboxEmail($customer, OutboxCodeEnum::CREDIT_BALANCE_NOTIFICATION_FOR_CUSTOMER, $additionalData);
    }
}

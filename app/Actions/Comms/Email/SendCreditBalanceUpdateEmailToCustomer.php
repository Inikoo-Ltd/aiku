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

class SendCreditBalanceUpdateEmailToCustomer extends OrgAction
{
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(Customer $customer, OutboxCodeEnum $outboxCodeEnum, array $additionalData = []): DispatchedEmail
    {
        return $this->sendCustomerOutboxEmail($customer, $outboxCodeEnum, $additionalData);
    }

}

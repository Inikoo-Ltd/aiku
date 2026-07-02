<?php

/*
 * author eka yudinata
 * created on 02-07-2026
 * github: https://github.com/ekayudinata
 * copyright 2026
*/

namespace App\Actions\Comms\Email;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;

// TOOO: This class is not used anywhere, consider removing it if not needed
class SendInvoicePaidEmailToCustomer extends OrgAction
{
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(Customer $customer, array $additionalData = []): DispatchedEmail|null
    {
        // data_set($additionalData, "customer_name", $customer->name);
        return $this->sendCustomerOutboxEmail($customer, OutboxCodeEnum::INVOICE_PAID, $additionalData);
    }
}

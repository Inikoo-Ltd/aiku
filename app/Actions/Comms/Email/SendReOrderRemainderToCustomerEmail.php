<?php

/*
 *  Author: Eka Yudinata <ekayudinata@gmail.com>
 *  Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2024, Eka Yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Models\Comms\EmailBulkRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

class SendReOrderRemainderToCustomerEmail implements ShouldQueue
{
    use AsAction;
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'low-priority';

    public function handle(Customer $customer, OutboxCodeEnum $outboxCodeEnum, EmailBulkRun $emailBulkRun): DispatchedEmail
    {
        return $this->sendCustomerOutboxEmail($customer, $outboxCodeEnum, [], '', null, null, $emailBulkRun);
    }
}

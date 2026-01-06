<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 16:02:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Models\Comms\Mailshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

class SendNewsletterToCustomerEmail implements ShouldQueue
{
    use AsAction;
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'ses';

    public function handle(Customer $customer, array $additionalData = [], Mailshot $mailshot): DispatchedEmail
    {
        return $this->sendCustomerOutboxEmail($customer, OutboxCodeEnum::NEWSLETTER, $additionalData, '', null, null, $mailshot);
    }
}

<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Log;
use App\Models\Comms\EmailBulkRun;

class SendReOrderRemainderToCustomerEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendCustomerOutboxEmail;


    public function handle(Customer $customer, EmailBulkRun $emailBulkRun): DispatchedEmail
    {
        return $this->sendCustomerOutboxEmail($customer, OutboxCodeEnum::REORDER_REMINDER, [], '', null, null, $emailBulkRun);
    }
}

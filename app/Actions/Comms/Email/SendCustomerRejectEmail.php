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
use App\Models\Comms\Email;
use App\Models\CRM\Customer;

class SendCustomerRejectEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendCustomerOutboxEmail;

    private Email $email;

    public function handle(Customer $customer): DispatchedEmail
    {
        return $this->sendCustomerOutboxEmail(
            $customer,
            OutboxCodeEnum::REGISTRATION_REJECTED,
            additionalData: [
                'rejected_notes' => $customer->rejected_notes,
            ]
        );
    }
}

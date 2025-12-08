<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Nov 2023 18:55:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;

trait WithCheckCanContactByEmail
{
    protected function canContactByEmail(Prospect|Customer $recipient): bool
    {
        return match (class_basename($recipient)) {
            'Prospect' => $this->canContactProspectByEmail($recipient),
            'Customer' => $this->canContactCustomerByEmail($recipient)
        };
    }

    protected function canContactCustomerByEmail(Customer $customer): bool
    {
        if (! filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    protected function canContactProspectByEmail(Prospect $prospect): bool
    {

        if (! $prospect->email) {
            return false;
        }

        if (in_array($prospect->fail_status, [
            ProspectFailStatusEnum::HARD_BOUNCED,
            ProspectFailStatusEnum::INVALID,
            ProspectFailStatusEnum::UNSUBSCRIBED,
        ])) {
            return false;
        }

        if ($prospect->dont_contact_me) {
            return false;
        }

        if (! filter_var($prospect->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Nov 2023 11:36:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;

trait WithCheckCanContactByPhone
{
    protected function canContactByPhone(Prospect|Customer $recipient): bool
    {
        return match (class_basename($recipient)) {
            'Prospect' => $this->canContactProspectByPhone($recipient),
            'Customer' => $this->canContactCustomerByPhone($recipient)
        };
    }

    protected function canContactCustomerByPhone(Customer $customer): bool
    {
        if (! $customer->phone) {
            return false;
        }

        return true;
    }

    protected function canContactProspectByPhone(Prospect $prospect): bool
    {
        if (! $prospect->phone) {
            return false;
        }

        return true;
    }
}

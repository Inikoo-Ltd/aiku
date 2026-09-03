<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\CRM\Customer\IsStaffCustomer;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\CRM\Customer;

class CustomerHydrateIsStaff
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:customer-is-staff {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = Customer::class;
    }

    public function handle(Customer $customer): void
    {
        $isStaff = IsStaffCustomer::filter(Customer::query()->whereKey($customer->id))->exists();

        if ($customer->is_staff !== $isStaff) {
            $customer->updateQuietly(['is_staff' => $isStaff]);
        }
    }
}

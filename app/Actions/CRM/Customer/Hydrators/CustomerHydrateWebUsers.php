<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateWebUsers implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $customerId): string
    {
        return (string) $customerId;
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $stats = [
            'number_web_users'         => $customer->webUsers->count(),
            'number_current_web_users' => $customer->webUsers->where('status', true)->count(),
        ];
        $customer->stats()->update($stats);
        $customer->refresh();
    }


}

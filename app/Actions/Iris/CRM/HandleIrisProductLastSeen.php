<?php

namespace App\Actions\Iris\CRM;

use App\Actions\IrisAction;
use App\Models\CRM\Customer;
use App\Models\CRM\ProductLastSeen;
use App\Models\Web\Webpage;
use Illuminate\Support\Carbon;

class HandleIrisProductLastSeen extends IrisAction
{
    public function handle(Webpage $webpage, ?Customer $customer, ?string $cookieId, ?Carbon $lastSeenAt = null): ProductLastSeen
    {
        return ProductLastSeen::updateOrCreate(
            [
                'webpage_id'  => $webpage->id,
                'customer_id' => $customer?->id,
                'cookie_id'   => $customer ? null : $cookieId,
            ],
            [
                'group_id'        => $webpage->group_id,
                'organisation_id' => $webpage->organisation_id,
                'shop_id'         => $webpage->shop_id,
                'last_seen_at'    => $lastSeenAt ?? now(),
            ]
        );
    }
}

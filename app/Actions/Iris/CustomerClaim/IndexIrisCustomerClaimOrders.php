<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CustomerClaim;

use App\Actions\IrisAction;
use App\Actions\CRM\CustomerClaim\GetCustomerClaimSettings;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

/**
 * The customer's orders they can still claim on, newest first, for "Problem with an order" in the
 * website chat (HELP-4869). Not found where the shop does not take claims from customers, so the
 * chat shows no button there.
 */
class IndexIrisCustomerClaimOrders extends IrisAction
{
    use WithIrisClaimCustomer;

    /**
     * @return array<int, array{id: int, reference: string, dispatched_at: string|null}>
     */
    public function handle(Customer $customer): array
    {
        return $customer->orders()
            ->whereIn('state', [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])
            ->where('dispatched_at', '>=', now()->subDays(GetCustomerClaimSettings::for($customer->shop)['claim_days']))
            ->latest('dispatched_at')
            ->limit(10)
            ->get(['id', 'reference', 'dispatched_at'])
            ->map(fn (Order $order) => ['id' => $order->id, 'reference' => $order->reference, 'dispatched_at' => $order->dispatched_at?->toIso8601String()])
            ->all();
    }

    /**
     * @return array<int, array{id: int, reference: string, dispatched_at: string|null}>
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->claimCustomer());
    }
}

<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetWixOrdersFromApi extends RetinaAction
{
    use WithActionUpdate;

    public const int FETCH_DAYS = 3;

    public string $commandSignature = 'wix:fetch-order {customerSalesChannel} {--days='.self::FETCH_DAYS.'}';

    /**
     * Catches orders whose webhook never arrived or was routed elsewhere. Only orders placed since the
     * channel was connected and within the last few days: importing one charges the customer, so an
     * older unfulfilled order the merchant handled another way must not be picked up (PRE-43).
     */
    public function handle(WixUser $wixUser, int $days = self::FETCH_DAYS): void
    {
        $from = now()->subDays($days)->max($wixUser->customerSalesChannel?->created_at ?? now());

        $response = $wixUser->searchOrders([
            'filter' => [
                'status'            => ['$eq' => 'APPROVED'],
                'fulfillmentStatus' => ['$eq' => 'NOT_FULFILLED'],
                'createdDate'       => ['$gte' => $from->toIso8601ZuluString()],
            ],
            'cursorPaging' => [
                'limit' => 100
            ],
        ]);

        foreach (Arr::get($response, 'orders', []) as $wixOrder) {
            ValidateIncomingWixOrder::run($wixUser, $wixOrder);
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user, (int) $command->option('days'));
    }
}

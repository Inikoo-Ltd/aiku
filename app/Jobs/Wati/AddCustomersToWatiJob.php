<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Jobs\Wati;

use App\Actions\Comms\Wati\AddCustomerToWati;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AddCustomersToWatiJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Shop $shop,
        public readonly array $customerIds,
    ) {
    }

    public function handle(): void
    {
        Customer::where('shop_id', $this->shop->id)
            ->whereIn('id', $this->customerIds)
            ->whereDoesntHave('watiContact')
            ->each(function (Customer $customer): void {
                try {
                    AddCustomerToWati::run($this->shop, $customer);
                } catch (\Throwable $e) {
                    Log::warning('AddCustomersToWatiJob: failed to add customer', [
                        'customer_id' => $customer->id,
                        'error'       => $e->getMessage(),
                    ]);
                }

                usleep(200_000);
            });
    }
}

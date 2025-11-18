<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 18 Nov 2025 14:29:22 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClv;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Support\Facades\Log;
use Sentry;

class HydrateCustomersClv
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:customers-clv {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = Customer::class;
    }

    public function handle(Customer $customer): void
    {
        try {
            CustomerHydrateClv::run($customer);
        } catch (Exception $e) {
            Log::info("Failed to Hydrate Customers Clv: " . $e->getMessage());
            Sentry::captureMessage("Failed to Hydrate Customers Clv to: " . $e->getMessage());
        }
    }
}

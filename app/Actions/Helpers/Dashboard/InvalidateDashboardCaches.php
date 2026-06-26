<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Dashboard;

use App\Models\Accounting\Invoice;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class InvalidateDashboardCaches
{
    use AsObject;

    public function handle(Invoice $invoice): void
    {
        Cache::tags(["dashboard-group-{$invoice->group_id}"])->flush();
        Cache::tags(["dashboard-org-{$invoice->organisation_id}"])->flush();
        Cache::tags(["dashboard-shop-{$invoice->shop_id}"])->flush();
    }
}

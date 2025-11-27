<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 15:20:21 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesMetrics;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesMetrics;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSalesMetrics;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesMetrics;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateSalesMetrics
{
    use AsAction;

    public function handle(): void
    {
        $groups = Group::all();
        $organisations = Organisation::all();
        $shops = Shop::all();
        $invoiceCategories = InvoiceCategory::all();

        $today = Carbon::today('UTC');

        foreach ($groups as $group) {
            GroupHydrateSalesMetrics::dispatch($group, $today);
        }

        foreach ($organisations as $organisation) {
            OrganisationHydrateSalesMetrics::dispatch($organisation, $today);
        }

        foreach ($shops as $shop) {
            ShopHydrateSalesMetrics::dispatch($shop, $today);
        }

        foreach ($invoiceCategories as $invoiceCategory) {
            InvoiceCategoryHydrateSalesMetrics::dispatch($invoiceCategory, $today);
        }
    }
}

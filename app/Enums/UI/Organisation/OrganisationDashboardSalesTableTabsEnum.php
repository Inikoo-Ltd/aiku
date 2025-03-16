<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 22:10:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Organisation;

use App\Actions\Accounting\InvoiceCategory\IndexInvoiceCategoriesSalesTable;
use App\Actions\Catalogue\Shop\IndexShopsSalesTable;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Models\SysAdmin\Organisation;

enum OrganisationDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOPS = 'shops';
    case INVOICE_CATEGORIES = 'invoice_categories';


    public function blueprint(): array
    {
        return match ($this) {
            OrganisationDashboardSalesTableTabsEnum::SHOPS => [
                'title' => __('Sales per shop'),
                'icon'  => 'fal fa-store',
            ],
            OrganisationDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => [
                'title' => __('Sales per invoice category'),
                'icon'  => 'fal fa-site-map',
            ],
        };
    }

    public function table(Organisation $organisation): array
    {
        return match ($this) {
            OrganisationDashboardSalesTableTabsEnum::SHOPS => IndexShopsSalesTable::make()->action($organisation),
            OrganisationDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => IndexInvoiceCategoriesSalesTable::make()->action($organisation),
        };
    }

    public static function tables(Organisation $organisation): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($organisation) {
            return [$case->value => $case->table($organisation)];
        })->all();
    }


}

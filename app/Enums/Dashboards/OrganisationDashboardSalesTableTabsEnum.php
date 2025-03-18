<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 22:10:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dashboards;

use App\Actions\Accounting\InvoiceCategory\IndexInvoiceCategoriesSalesTable;
use App\Actions\Catalogue\Shop\IndexShopsSalesTable;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardHeaderInvoiceCategoriesSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderShopsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalInvoiceCategoriesSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalShopSalesResource;
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
                'icon'  => 'fal fa-sitemap',
            ],
        };
    }

    public function table(Organisation $organisation): array
    {

        $header = match ($this) {
            OrganisationDashboardSalesTableTabsEnum::SHOPS => json_decode(DashboardHeaderShopsSalesResource::make($organisation)->toJson(), true),
            OrganisationDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => json_decode(DashboardHeaderInvoiceCategoriesSalesResource::make($organisation)->toJson(), true)
        };

        $body = match ($this) {
            OrganisationDashboardSalesTableTabsEnum::SHOPS => IndexShopsSalesTable::make()->action($organisation),
            OrganisationDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => IndexInvoiceCategoriesSalesTable::make()->action($organisation),
        };

        $totals = match ($this) {
            OrganisationDashboardSalesTableTabsEnum::SHOPS => json_decode(DashboardTotalShopSalesResource::make($organisation)->toJson(), true),
            OrganisationDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => json_decode(DashboardTotalInvoiceCategoriesSalesResource::make($organisation)->toJson(), true)
        };


        return [
            'header' => $header,
            'body'   => $body,
            'totals' => $totals
        ];
    }

    public static function tables(Organisation $organisation): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($organisation) {
            return [$case->value => $case->table($organisation)];
        })->all();
    }


}

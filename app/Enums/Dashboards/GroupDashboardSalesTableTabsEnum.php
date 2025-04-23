<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dashboards;

use App\Actions\Accounting\InvoiceCategory\IndexInvoiceCategoriesSalesTable;
use App\Actions\Dashboard\IndexOrganisationsSalesTable;
use App\Actions\Dashboard\IndexShopsSalesTable;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardHeaderInvoiceCategoriesInGroupSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderShopsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalGroupInvoiceCategoriesSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalGroupShopsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalOrganisationsSalesResource;
use App\Http\Resources\SysAdmin\DashboardHeaderOrganisationsSalesResource;
use App\Models\SysAdmin\Group;

enum GroupDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ORGANISATIONS = 'organisations';
    case SHOPS = 'shops';
    case INVOICE_CATEGORIES = 'invoice_categories';


    public function blueprint(): array
    {
        return match ($this) {
            GroupDashboardSalesTableTabsEnum::ORGANISATIONS => [
                'title' => __('Organisations'),
                'icon'  => 'fal fa-city',
            ],
            GroupDashboardSalesTableTabsEnum::SHOPS => [
                'title' => __('Shops'),
                'icon'  => 'fal fa-store-alt',
            ],
            GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => [
                'title' => __('Invoice categories'),
                'icon'  => 'fal fa-sitemap',
            ],
        };
    }


    public function table(Group $group): array
    {

        $header = match ($this) {
            GroupDashboardSalesTableTabsEnum::ORGANISATIONS => json_decode(DashboardHeaderOrganisationsSalesResource::make($group)->toJson(), true),
            GroupDashboardSalesTableTabsEnum::SHOPS => json_decode(DashboardHeaderShopsSalesResource::make($group)->toJson(), true),
            GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => json_decode(DashboardHeaderInvoiceCategoriesInGroupSalesResource::make($group)->toJson(), true)
        };

        $body = match ($this) {
            GroupDashboardSalesTableTabsEnum::ORGANISATIONS => IndexOrganisationsSalesTable::make()->action($group),
            GroupDashboardSalesTableTabsEnum::SHOPS => IndexShopsSalesTable::make()->action($group),
            GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => IndexInvoiceCategoriesSalesTable::make()->action($group),
        };

        $totals = match ($this) {
            GroupDashboardSalesTableTabsEnum::ORGANISATIONS => json_decode(DashboardTotalOrganisationsSalesResource::make($group)->toJson(), true),
            GroupDashboardSalesTableTabsEnum::SHOPS => json_decode(DashboardTotalGroupShopsSalesResource::make($group)->toJson(), true),
            GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => json_decode(DashboardTotalGroupInvoiceCategoriesSalesResource::make($group)->toJson(), true)
        };




        return [
            'header' => $header,
            'body'   => $body,
            'totals' => $totals
        ];
    }

    public static function tables(Group $group): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($group) {
            return [$case->value => $case->table($group)];
        })->all();
    }


}

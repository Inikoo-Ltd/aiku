<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dashboards;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardHeaderInvoiceCategoriesInGroupSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderSalesChannelsSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderShopsSalesResource;
use App\Http\Resources\Dashboards\DashboardInvoiceCategoriesInGroupSalesResource;
use App\Http\Resources\Dashboards\DashboardOrganisationSalesResource;
use App\Http\Resources\Dashboards\DashboardPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardSalesChannelSalesResource;
use App\Http\Resources\Dashboards\DashboardShopSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalGroupInvoiceCategoriesSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalOrganisationsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalSalesChannelsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalShopsTimeSeriesSalesResource;
use App\Http\Resources\SysAdmin\DashboardHeaderOrganisationsSalesResource;
use App\Models\SysAdmin\Group;

enum GroupDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ORGANISATIONS = 'organisations';
    case SHOPS = 'shops';
    case INVOICE_CATEGORIES = 'invoice_categories';
    case GLOBAL_MARKETPLACES = 'global_marketplaces';
    case GLOBAL_DROPSHIPPING = 'global_dropshipping';
    case GLOBAL_FULFILMENT = 'global_fulfilment';

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
                'title' => __('Invoice Categories'),
                'icon'  => 'fal fa-sitemap',
            ],
            GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => [
                'title' => __('Global Marketplaces'),
                'icon'  => 'fal fa-gift-card',
            ],
            GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => [
                'title' => __('Global Dropshipping'),
                'icon'  => 'fal fa-parachute-box',
            ],
            GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => [
                'title' => __('Global Fulfilment'),
                'icon'  => 'fal fa-pallet-alt',
            ],
        };
    }

    public function table(Group $group, array $timeSeriesData = [], ?bool $bool = false): array
    {
        if ($bool && $this !== self::GLOBAL_DROPSHIPPING) {
            return [];
        }

        $organisationTimeSeriesStats = $timeSeriesData['organisations'];
        $shopTimeSeriesStats = $timeSeriesData['shops']['all'];
        $invoiceCategoryTimeSeriesStats = $timeSeriesData['invoiceCategories'];
        $platformTimeSeriesStats = $timeSeriesData['platforms'];
        $salesChannelTimeSeriesStats = $timeSeriesData['salesChannels'];
        $dropshippingShopTimeSeriesStats = $timeSeriesData['shops']['dropshipping'];
        $fulfilmentShopTimeSeriesStats = $timeSeriesData['shops']['fulfilment'];

        if (!$bool) {
            $header = match ($this) {
                GroupDashboardSalesTableTabsEnum::ORGANISATIONS => json_decode(DashboardHeaderOrganisationsSalesResource::make($group)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::SHOPS => json_decode(DashboardHeaderShopsSalesResource::make($group)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => json_decode(DashboardHeaderInvoiceCategoriesInGroupSalesResource::make($group)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => json_decode(DashboardHeaderSalesChannelsSalesResource::make($group)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => json_decode(DashboardHeaderShopsSalesResource::make($group)->withContext($this)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => json_decode(DashboardHeaderShopsSalesResource::make($group)->withContext($this)->toJson(), true),
            };

            $body = match ($this) {
                GroupDashboardSalesTableTabsEnum::ORGANISATIONS => json_decode(DashboardOrganisationSalesResource::collection($organisationTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::SHOPS => json_decode(DashboardShopSalesResource::collection($shopTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => json_decode(DashboardInvoiceCategoriesInGroupSalesResource::collection($invoiceCategoryTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => json_decode(DashboardSalesChannelSalesResource::collection($salesChannelTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => json_decode(DashboardShopSalesResource::collection($dropshippingShopTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => json_decode(DashboardShopSalesResource::collection($fulfilmentShopTimeSeriesStats)->toJson(), true),
            };

            $totals = match ($this) {
                GroupDashboardSalesTableTabsEnum::ORGANISATIONS => json_decode(DashboardTotalOrganisationsSalesResource::make($organisationTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::SHOPS => json_decode(DashboardTotalShopsTimeSeriesSalesResource::make($shopTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => json_decode(DashboardTotalGroupInvoiceCategoriesSalesResource::make($invoiceCategoryTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => json_decode(DashboardTotalSalesChannelsSalesResource::make($salesChannelTimeSeriesStats)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => json_decode(DashboardTotalShopsTimeSeriesSalesResource::make($dropshippingShopTimeSeriesStats)->withContext($this)->toJson(), true),
                GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => json_decode(DashboardTotalShopsTimeSeriesSalesResource::make($fulfilmentShopTimeSeriesStats)->withContext($this)->toJson(), true),
            };
        } else {
            $header = match ($this) {
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => json_decode(DashboardHeaderPlatformSalesResource::make($group)->toJson(), true),
            };

            $body = match ($this) {
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => json_decode(DashboardPlatformSalesResource::collection($platformTimeSeriesStats)->toJson(), true),
            };

            $totals = match ($this) {
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => json_decode(DashboardTotalPlatformSalesResource::make($platformTimeSeriesStats)->toJson(), true),
            };
        }

        return [
            'header' => $header,
            'body'   => $body,
            'totals' => $totals
        ];
    }

    public static function tables(Group $group, array $timeSeriesData = [], ?bool $bool = false): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($group, $timeSeriesData, $bool) {
            return [$case->value => $case->table($group, $timeSeriesData, $bool)];
        })->filter()->all();
    }
}

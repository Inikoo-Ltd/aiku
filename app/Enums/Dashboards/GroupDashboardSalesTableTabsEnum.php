<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dashboards;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderInvoiceCategoriesInGroupSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderSalesChannelsSalesResource;
use App\Http\Resources\Dashboards\DashboardHeaderShopsSalesResource;
use App\Http\Resources\Dashboards\DashboardInvoiceCategoriesInGroupSalesResource;
use App\Http\Resources\Dashboards\DashboardOrganisationSalesResource;
use App\Http\Resources\Dashboards\DashboardPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardSalesChannelSalesResource;
use App\Http\Resources\Dashboards\DashboardShopSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalBrandSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalGroupInvoiceCategoriesSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalOrganisationsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalSalesChannelsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalShopsTimeSeriesSalesResource;
use App\Http\Resources\SysAdmin\DashboardHeaderOrganisationsSalesResource;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\JsonResource;

enum GroupDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ORGANISATIONS = 'organisations';
    case SHOPS = 'shops';
    case BRANDS = 'brands';
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
            GroupDashboardSalesTableTabsEnum::BRANDS => [
                'title' => __('Brands'),
                'icon'  => 'fal fa-copyright',
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
        if ($bool && !in_array($this, [self::GLOBAL_DROPSHIPPING, self::GLOBAL_MARKETPLACES])) {
            return [];
        }

        $organisationTimeSeriesStats = $timeSeriesData['organisations'];
        $shopTimeSeriesStats = $timeSeriesData['shops']['all'];
        $brandTimeSeriesStats = $timeSeriesData['brands'];
        $invoiceCategoryTimeSeriesStats = $timeSeriesData['invoiceCategories'];
        $platformTimeSeriesStats = $timeSeriesData['platforms'];
        $salesChannelTimeSeriesStats = $timeSeriesData['salesChannels'];
        $dropshippingShopTimeSeriesStats = $timeSeriesData['shops']['dropshipping'];
        $fulfilmentShopTimeSeriesStats = $timeSeriesData['shops']['fulfilment'];
        $faireTimeSeriesStats = $timeSeriesData['faire'];

        if (!$bool) {
            $header = match ($this) {
                GroupDashboardSalesTableTabsEnum::ORGANISATIONS => self::resourceToArray(DashboardHeaderOrganisationsSalesResource::make($group)),
                GroupDashboardSalesTableTabsEnum::SHOPS => self::resourceToArray(DashboardHeaderShopsSalesResource::make($group)),
                GroupDashboardSalesTableTabsEnum::BRANDS => self::resourceToArray(DashboardHeaderBrandSalesResource::make($group)),
                GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => self::resourceToArray(DashboardHeaderInvoiceCategoriesInGroupSalesResource::make($group)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => self::resourceToArray(DashboardHeaderSalesChannelsSalesResource::make($group)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => self::resourceToArray(DashboardHeaderShopsSalesResource::make($group)->withContext($this)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => self::resourceToArray(DashboardHeaderShopsSalesResource::make($group)->withContext($this)),
            };

            $body = match ($this) {
                GroupDashboardSalesTableTabsEnum::ORGANISATIONS => self::resourceToArray(DashboardOrganisationSalesResource::collection($organisationTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::SHOPS => self::resourceToArray(DashboardShopSalesResource::collection($shopTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::BRANDS => self::resourceToArray(DashboardBrandSalesResource::collection($brandTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => self::resourceToArray(DashboardInvoiceCategoriesInGroupSalesResource::collection($invoiceCategoryTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => self::resourceToArray(DashboardSalesChannelSalesResource::collection($salesChannelTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => self::resourceToArray(DashboardShopSalesResource::collection($dropshippingShopTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => self::resourceToArray(DashboardShopSalesResource::collection($fulfilmentShopTimeSeriesStats)),
            };

            $totals = match ($this) {
                GroupDashboardSalesTableTabsEnum::ORGANISATIONS => self::resourceToArray(DashboardTotalOrganisationsSalesResource::make($organisationTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::SHOPS => self::resourceToArray(DashboardTotalShopsTimeSeriesSalesResource::make($shopTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::BRANDS => self::resourceToArray(DashboardTotalBrandSalesResource::make($brandTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::INVOICE_CATEGORIES => self::resourceToArray(DashboardTotalGroupInvoiceCategoriesSalesResource::make($invoiceCategoryTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => self::resourceToArray(DashboardTotalSalesChannelsSalesResource::make($salesChannelTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => self::resourceToArray(DashboardTotalShopsTimeSeriesSalesResource::make($dropshippingShopTimeSeriesStats)->withContext($this)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => self::resourceToArray(DashboardTotalShopsTimeSeriesSalesResource::make($fulfilmentShopTimeSeriesStats)->withContext($this)),
            };
        } else {
            $header = match ($this) {
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => self::resourceToArray(DashboardHeaderPlatformSalesResource::make($group)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => self::resourceToArray(DashboardHeaderInvoiceCategoriesInGroupSalesResource::make($group)),
            };

            $body = match ($this) {
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => self::resourceToArray(DashboardPlatformSalesResource::collection($platformTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => self::resourceToArray(DashboardInvoiceCategoriesInGroupSalesResource::collection($faireTimeSeriesStats)),
            };

            $totals = match ($this) {
                GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => self::resourceToArray(DashboardTotalPlatformSalesResource::make($platformTimeSeriesStats)),
                GroupDashboardSalesTableTabsEnum::GLOBAL_MARKETPLACES => self::resourceToArray(DashboardTotalGroupInvoiceCategoriesSalesResource::make($faireTimeSeriesStats)),
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
        if ($bool) {
            return [
                self::GLOBAL_DROPSHIPPING->value => self::GLOBAL_DROPSHIPPING->table($group, $timeSeriesData, true),
                self::GLOBAL_MARKETPLACES->value => self::GLOBAL_MARKETPLACES->table($group, $timeSeriesData, true),
            ];
        }

        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->table($group, $timeSeriesData)])
            ->filter()
            ->all();
    }

    public static function tablesForTabs(
        Group $group,
        array $timeSeriesData,
        array $tabs,
        bool $isSecondBlock = false
    ): array {
        return collect($tabs)
            ->map(function ($tab) {
                if ($tab instanceof self) {
                    return $tab;
                }

                return self::tryFrom((string) $tab);
            })
            ->filter()
            ->mapWithKeys(fn (self $tab) => [$tab->value => $tab->table($group, $timeSeriesData, $isSecondBlock)])
            ->filter()
            ->all();
    }

    private static function resourceToArray(JsonResource $resource): array
    {
        return $resource->resolve(request());
    }
}

<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 27 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Dashboards;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\GroupStockHistory;
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithLatestStockHistory
{
    protected function getGroupStockHistoryData(Group $group): ?array
    {
        $groupHistory = GroupStockHistory::query()
            ->where('group_id', $group->id)
            ->where('is_week', false)
            ->where('is_month', false)
            ->where('is_year', false)
            ->latest('date')
            ->first();

        if (!$groupHistory) {
            return null;
        }

        $orgHistoriesQuery = OrganisationStockHistory::query()
            ->where('group_stock_history_id', $groupHistory->id)
            ->whereHas('organisation', fn ($q) => $q->where('type', OrganisationTypeEnum::SHOP));

        $totalNotSold1y = (clone $orgHistoriesQuery)->sum('number_org_stocks_not_sold_1y');

        $orgHistories = (clone $orgHistoriesQuery)
            ->with([
                'organisation.currency',
                'organisation.warehouses' => fn ($q) => $q->select(['warehouses.id', 'warehouses.slug', 'warehouses.organisation_id']),
            ])
            ->get();

        $totalSkus       = $groupHistory->number_org_stocks;
        $totalOutOfStock = $groupHistory->number_out_of_stock_org_stocks;
        $totalLocations  = $groupHistory->number_locations;
        $stockValue      = (float) $groupHistory->grp_stock_value;
        $dormant1y       = (float) $groupHistory->grp_value_dormant_stock_1y;

        $pctOutOfStock = $totalSkus > 0 ? round($totalOutOfStock / $totalSkus * 100, 1) : 0;
        $pctDormant1y  = $groupHistory->percentage_value_dormant_stock_1y ?? 0;
        $pctNotSold1y  = $totalSkus > 0 ? round($totalNotSold1y / $totalSkus * 100, 1) : 0;

        return [
            'date'                           => $groupHistory->date->toDateString(),
            'number_org_stocks'              => $totalSkus,
            'number_out_of_stock_org_stocks' => $totalOutOfStock,
            'percentage_out_of_stock'        => $pctOutOfStock,
            'number_locations'               => $totalLocations,
            'grp_stock_value'                => $stockValue,
            'currency_code'                  => $group->currency->code,
            'grp_value_dormant_stock_1y'     => $dormant1y,
            'percentage_dormant_1y'          => $pctDormant1y,
            'number_org_stocks_not_sold_1y'  => $totalNotSold1y,
            'percentage_not_sold_1y'         => $pctNotSold1y,
            'organisations'                  => $orgHistories->map(function ($history) {
                $org           = $history->organisation;
                $orgSlug       = $org->slug;
                $warehouseSlug = $org->warehouses->first()?->slug;

                $routeParams = ['organisation' => $orgSlug, 'warehouse' => $warehouseSlug];

                return [
                    'name'                           => $org->name,
                    'slug'                           => $orgSlug,
                    'currency_code'                  => $org->currency->code,
                    'number_org_stocks'              => $history->number_org_stocks,
                    'number_out_of_stock_org_stocks' => $history->number_out_of_stock_org_stocks,
                    'percentage_out_of_stock'        => $history->percentage_out_of_stock,
                    'number_locations'               => $history->number_locations,
                    'org_stock_value'                => (float) $history->org_stock_value,
                    'value_dormant_stock_1y'         => (float) $history->value_dormant_stock_1y,
                    'percentage_dormant_1y'          => $history->percentage_value_dormant_stock_1y ?? 0,
                    'number_org_stocks_not_sold_1y'  => $history->number_org_stocks_not_sold_1y,
                    'percentage_not_sold_1y'         => $history->number_org_stocks > 0
                        ? round($history->number_org_stocks_not_sold_1y / $history->number_org_stocks * 100, 1)
                        : 0,
                    'routes'                         => $warehouseSlug ? [
                        'dashboard'     => [
                            'name'       => 'grp.org.warehouses.show.inventory.dashboard',
                            'parameters' => $routeParams,
                        ],
                        'history'       => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_histories.show',
                            'parameters' => array_merge($routeParams, ['organisationStockHistory' => $history->id]),
                        ],
                        'locations'     => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                            'parameters' => $routeParams,
                        ],
                    ] : null,
                ];
            })->values()->toArray(),
        ];
    }

    protected function getOrganisationStockHistoryData(Organisation $organisation, ?array $routeParameters = null): ?array
    {
        $history = OrganisationStockHistory::query()
            ->where('organisation_id', $organisation->id)
            ->where('is_week', false)
            ->where('is_month', false)
            ->where('is_year', false)
            ->latest('date')
            ->first();

        if (!$history) {
            return null;
        }

        $totalSkus    = $history->number_org_stocks;
        $pctNotSold1y = $totalSkus > 0
            ? round($history->number_org_stocks_not_sold_1y / $totalSkus * 100, 1)
            : 0;

        $data = [
            'date'                           => $history->date->toDateString(),
            'number_org_stocks'              => $totalSkus,
            'number_out_of_stock_org_stocks' => $history->number_out_of_stock_org_stocks,
            'percentage_out_of_stock'        => $history->percentage_out_of_stock,
            'number_locations'               => $history->number_locations,
            'org_stock_value'                => $history->org_stock_value,
            'currency_code'                  => $organisation->currency->code,
            'value_dormant_stock_1y'         => $history->value_dormant_stock_1y,
            'percentage_dormant_1y'          => $history->percentage_value_dormant_stock_1y ?? 0,
            'number_org_stocks_not_sold_1y'  => $history->number_org_stocks_not_sold_1y,
            'percentage_not_sold_1y'         => $pctNotSold1y,
        ];

        if ($routeParameters) {
            $data['history_route'] = [
                'name'       => 'grp.org.warehouses.show.inventory.org_stock_histories.show',
                'parameters' => array_merge($routeParameters, ['organisationStockHistory' => $history->id]),
            ];
            $data['locations_route'] = [
                'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                'parameters' => $routeParameters,
            ];
        }

        return $data;
    }
}

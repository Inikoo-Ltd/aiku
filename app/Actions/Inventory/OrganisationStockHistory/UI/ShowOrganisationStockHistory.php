<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 20:41:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrganisationStockHistory\UI;

use App\Actions\Inventory\OrgStock\UI\IndexOrgStocksInLocation;
use App\Actions\Inventory\OrgStockHistory\UI\IndexOrgStockHistories;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Inventory\LocationTabsEnum;
use App\Enums\UI\Inventory\OrganisationStockHistoryTabsEnum;
use App\Http\Resources\Inventory\OrgStockHistoryResource;
use App\Http\Resources\Inventory\OrgStockResource;
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrganisationStockHistory extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, OrganisationStockHistory $organisationStockHistory, ActionRequest $request): OrganisationStockHistory
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrganisationStockHistoryTabsEnum::values());

        return $this->handle($organisationStockHistory);
    }

    public function handle(OrganisationStockHistory $organisationStockHistory): OrganisationStockHistory
    {
        return $organisationStockHistory;
    }



    public function htmlResponse(OrganisationStockHistory $organisationStockHistory, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Warehouse/Location',
            [
                'title' => __('Stock History').' '.$organisationStockHistory->date->format('D, M j, Y'),

                'pageHead' => [
                    'icon' => [
                        'title' => __('Stock History').' '.$organisationStockHistory->date->format('D, M j, Y'),
                        'icon'  => 'fal fa-inventory'
                    ],

                    'title' => __('Stock History').' '.$organisationStockHistory->date->format('D, M j, Y'),

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrganisationStockHistoryTabsEnum::navigation()

                ],

                OrganisationStockHistoryTabsEnum::ORG_STOCKS->value => $this->tab == OrganisationStockHistoryTabsEnum::ORG_STOCKS->value ?
                    fn () => OrgStockHistoryResource::collection(IndexOrgStockHistories::run($organisationStockHistory))
                    : Inertia::lazy(fn () => OrgStockHistoryResource::collection(IndexOrgStockHistories::run($organisationStockHistory))),
                OrganisationStockHistoryTabsEnum::LOCATION_ORG_STOCKS->value => $this->tab == OrganisationStockHistoryTabsEnum::LOCATION_ORG_STOCKS->value ?
                    fn () => OrgStockHistoryResource::collection(IndexOrgStockHistories::run($organisationStockHistory))
                    : Inertia::lazy(fn () => OrgStockHistoryResource::collection(IndexOrgStockHistories::run($organisationStockHistory))),

            ]
        )->table(IndexOrgStockHistories::make()->tableStructure($organisationStockHistory, prefix: OrganisationStockHistoryTabsEnum::ORG_STOCKS->value))
            ->table(IndexOrgStockHistories::make()->tableStructure($organisationStockHistory, prefix: OrganisationStockHistoryTabsEnum::LOCATION_ORG_STOCKS->value));



    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_histories.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Stock History'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}

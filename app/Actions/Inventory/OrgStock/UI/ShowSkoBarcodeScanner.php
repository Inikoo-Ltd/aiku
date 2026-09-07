<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSkoBarcodeScanner extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        $routeParameters = $request->route()->originalParameters();

        return Inertia::render('Org/Inventory/SkoBarcodeScanner', [
            'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
            'title'       => __('SKO barcodes'),
            'pageHead'    => [
                'title' => __('SKO barcodes'),
                'model' => __('Inventory'),
                'icon'  => ['icon' => 'fal fa-barcode-read'],
            ],
            'can_edit'     => $this->canEdit,
            'scan_route'   => [
                'name'       => 'grp.json.warehouse.scan_sko_barcode',
                'parameters' => ['warehouse' => $warehouse->slug],
            ],
            'search_route' => [
                'name'       => 'grp.json.org_stocks.index',
                'parameters' => ['organisation' => $organisation->id],
            ],
            'assign_route' => [
                'name'       => 'grp.org.warehouses.show.inventory.org_stocks.assign_sko_barcode',
                'parameters' => $routeParameters,
            ],
        ]);
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
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.barcode_scanner',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('SKO barcodes'),
                    ],
                ],
            ]
        );
    }
}

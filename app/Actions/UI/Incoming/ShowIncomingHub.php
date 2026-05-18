<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 12:57:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Incoming;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\UI\Incoming\IncomingHubTabsEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIncomingHub extends OrgAction
{
    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("incoming.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, [])->withTab(IncomingHubTabsEnum::values());

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Incoming/IncomingHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => 'incoming',
                'pageHead' => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-arrow-to-bottom'],
                        'title' => __('Incoming')
                    ],
                    'title' => __('Incoming Hub'),
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => IncomingHubTabsEnum::navigation()
                ],
                'stock_deliveries'      => GetIncomingHubStockDeliveryWidget::run($warehouse),
                'pallet_deliveries'     => GetIncomingHubPalletDeliveryWidget::run($warehouse),
                'return_delivery_notes' => GetIncomingHubReturnDeliveryNoteWidget::run($warehouse),
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.incoming.backlog',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => ['fal', 'fa-arrow-to-bottom'],
                        'label' => __('Goods in'),
                    ]
                ]
            ]
        );
    }
}

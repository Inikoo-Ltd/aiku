<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:44:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\UI\Dispatch\DispatchHubTabsEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDispatchHub extends OrgAction
{
    use WithDispatchingAuthorisation;

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, [])->withTab(DispatchHubTabsEnum::values());

        return $this->handle($warehouse);
    }


    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/DispatchHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => 'dispatch',
                'pageHead'    => [
                    'icon'  => [
                        'icon' => ['fal', 'fa-conveyor-belt-alt'],
                    ],
                    'title' => __('Dispatching backlog'),
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => DispatchHubTabsEnum::navigation()
                ],

                DispatchHubTabsEnum::DASHBOARD->value => $this->tab == DispatchHubTabsEnum::DASHBOARD->value
                ? fn () => GetDispatchHubShowcase::make()->handle($warehouse, $request)
                : Inertia::lazy(fn () => GetDispatchHubShowcase::make()->handle($warehouse, $request)),

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
                            'name'       => 'grp.org.warehouses.show.dispatching.backlog',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Dispatching'),
                    ]
                ]
            ]
        );
    }

}

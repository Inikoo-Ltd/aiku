<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:16:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseAuthorisation;
use App\Enums\UI\Dispatch\TrolleyTabsEnum;
use App\Http\Resources\Dispatching\TrolleyResource;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTrolley extends OrgAction
{
    use WithActionButtons;
    use WithWarehouseAuthorisation;

    public function handle(Trolley $trolley): Trolley
    {
        return $trolley;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, Trolley $trolley, ActionRequest $request): Trolley
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(TrolleyTabsEnum::values());

        return $this->handle($trolley);
    }

    public function htmlResponse(Trolley $trolley, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/Trolley',
            [
                'title'       => __('Trolley') . ' ' . $trolley->name,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($trolley, $request),
                    'next'     => $this->getNext($trolley, $request),
                ],
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'dolly-flatbed-alt'],
                            'title' => __('Trolley')
                        ],
                    'title'   => $trolley->name,
                    'model'   => __('Trolley'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.trolleys.edit',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => TrolleyTabsEnum::navigation(),
                ],

                TrolleyTabsEnum::SHOWCASE->value => $this->tab == TrolleyTabsEnum::SHOWCASE->value ?
                    fn () => GetTrolleyShowcase::run($trolley)
                    : Inertia::lazy(fn () => GetTrolleyShowcase::run($trolley)),

            ]
        );
    }


    public function jsonResponse(Warehouse $warehouse): TrolleyResource
    {
        return new TrolleyResource($warehouse);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        return array_merge(
            IndexTrolleys::make()->getBreadcrumbs(
                routeName: preg_replace('/show$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($routeParameters['trolley'])
                    ],
                    'suffix' => $suffix
                ],
            ]
        );
    }

    public function getPrevious(Trolley $trolley, ActionRequest $request): ?array
    {
        $previous = Trolley::where('name', '<', $trolley->name)
            ->where('organisation_id', $trolley->organisation_id)->orderBy('name', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Trolley $trolley, ActionRequest $request): ?array
    {
        $next = Trolley::where('name', '>', $trolley->name)->where('organisation_id', $trolley->organisation_id)->orderBy('name')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Trolley $trolley, string $routeName): ?array
    {
        if (!$trolley) {
            return null;
        }

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.trolleys.show' => [
                'label' => $trolley->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $trolley->warehouse->slug,
                        'trolley'      => $trolley->slug
                    ]
                ]
            ]
        };
    }
}

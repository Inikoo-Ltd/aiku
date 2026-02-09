<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\PickedBay\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseAuthorisation;
use App\Enums\UI\Inventory\PickedBayTabsEnum;
use App\Http\Resources\Inventory\PickedBayResource;
use App\Models\Inventory\PickedBay;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPickedBay extends OrgAction
{
    use WithActionButtons;
    use WithWarehouseAuthorisation;

    public function handle(PickedBay $pickedBay): PickedBay
    {
        return $pickedBay;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("inventory.{$this->warehouse->id}.edit");

        return $request->user()->authTo("inventory.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PickedBay $pickedBay, ActionRequest $request): PickedBay
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PickedBayTabsEnum::values());

        return $this->handle($pickedBay);
    }

    public function htmlResponse(PickedBay $pickedBay, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Warehouse/PickedBay',
            [
                'title'       => __('Warehouse'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($pickedBay, $request),
                    'next'     => $this->getNext($pickedBay, $request),
                ],
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'monument'],
                            'title' => __('picked bay')
                        ],
                    'title'   => $pickedBay->code,
                    'model'   => __('Picked bay'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picked_bays.edit',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'tabs'     => [
                    'current'    => $this->tab,
                    'navigation' => PickedBayTabsEnum::navigation(),
                ],

                PickedBayTabsEnum::SHOWCASE->value => $this->tab == PickedBayTabsEnum::SHOWCASE->value ?
                    fn () => GetPickedBayShowcase::run($pickedBay)
                    : Inertia::lazy(fn () => GetPickedBayShowcase::run($pickedBay)),

            ]
        );
    }


    public function jsonResponse(Warehouse $warehouse): PickedBayResource
    {
        return new PickedBayResource($warehouse);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        return array_merge(
            IndexPickedBays::make()->getBreadcrumbs(
                routeName: preg_replace('/show$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($routeParameters['pickedBay'])
                    ],
                    'suffix' => $suffix
                ],
            ]
        );
    }

    public function getPrevious(PickedBay $pickedBay, ActionRequest $request): ?array
    {
        $previous = PickedBay::where('code', '<', $pickedBay->code)->where('organisation_id', $pickedBay->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PickedBay $pickedBay, ActionRequest $request): ?array
    {
        $next = PickedBay::where('code', '>', $pickedBay->code)->where('organisation_id', $pickedBay->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PickedBay $pickedBay, string $routeName): ?array
    {
        if (!$pickedBay) {
            return null;
        }

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.picked_bays.show' => [
                'label' => $pickedBay->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse' => $pickedBay->warehouse->slug,
                        'pickedBay'    => $pickedBay->slug
                    ]
                ]
            ]
        };
    }
}

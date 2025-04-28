<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Warehouse\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseManagementEditAuthorisation;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditWarehouse extends OrgAction
{
    use WithWarehouseManagementEditAuthorisation;

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $sections               = [];
        $sections['properties'] = [
            'label'  => __('Properties'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'code' => [
                    'type'  => 'input',
                    'label' => __('code'),
                    'value' => $warehouse->code
                ],
                'name' => [
                    'type'  => 'input',
                    'label' => __('name'),
                    'value' => $warehouse->name
                ],
                'address' => [
                    'type'    => 'address',
                    'label'   => __('Address'),
                    'value'   => AddressFormFieldsResource::make($warehouse->address)->getArray(),
                    'options' => [
                        'countriesAddressData' => GetAddressData::run()
                    ]
                ],
            ]
        ];

        $currentSection = 'properties';
        if ($request->has('section') && Arr::has($sections, $request->get('section'))) {
            $currentSection = $request->get('section');
        }

        return Inertia::render(
            'EditModel',
            [
                'title'                            => __('edit warehouse'),
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($warehouse, $request),
                    'next'     => $this->getNext($warehouse, $request),
                ],
                'pageHead'    => [
                    'title'     => $warehouse->code,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'dashboard', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'current'   => $currentSection,
                    'blueprint' => $sections,
                    'args'      => [
                        'updateRoute' => [
                            'name'      => 'grp.models.warehouse.update',
                            'parameters' => $warehouse->id
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return ShowWarehouse::make()->getBreadcrumbs(routeParameters:$routeParameters, suffix: '('.__('Editing').')');
    }

    public function getPrevious(Warehouse $warehouse, ActionRequest $request): ?array
    {
        $previous = Warehouse::where('code', '<', $warehouse->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Warehouse $warehouse, ActionRequest $request): ?array
    {
        $next = Warehouse::where('code', '>', $warehouse->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Warehouse $warehouse, string $routeName): ?array
    {
        if (!$warehouse) {
            return null;
        }

        return match ($routeName) {
            'grp.org.warehouses.show.infrastructure.edit' => [
                'label' => $warehouse->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $warehouse->organisation->slug,
                        'warehouse'    => $warehouse->slug
                    ]
                ]
            ]
        };
    }
}

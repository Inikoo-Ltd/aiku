<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jan 2024 09:36:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\GrpAction;
use App\Actions\SupplyChain\HasSupplyChainFields;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateSupplier extends GrpAction
{
    use HasSupplyChainFields;

    private Group|Agent $parent;

    public function handle(Group|Agent $parent, ActionRequest $request): Response
    {
        if ($parent instanceof Agent) {
            $route = [
                'name'  => 'grp.models.agent.supplier.store',
                'parameters' => $parent->id
            ];
        } else {
            $route = [
                'name'  => 'grp.models.supplier.store',
            ];
        }

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New Supplier'),
                'pageHead'    => [
                    'title'        => __('New Supplier'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => $this->supplyChainFields(),
                    'route'     => $route
                ],
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('supply-chain.edit');
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();
        $this->initialisation($group, $request);

        return $this->handle($group, $request);
    }

    public function inAgent(Agent $agent, ActionRequest $request): Response
    {
        $group = $agent->group;
        $this->initialisation($group, $request);

        return $this->handle($agent, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexSuppliers::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("creating supplier"),
                    ]
                ]
            ]
        );
    }
}

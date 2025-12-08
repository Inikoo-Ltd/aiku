<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 18:20:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\GrpAction;
use App\Actions\SupplyChain\HasSupplyChainFields;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateAgent extends GrpAction
{
    use HasSupplyChainFields;

    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title' => __('New agent'),
                'pageHead' => [
                    'title' => __('New agent'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name' => 'grp.supply-chain.agents.index',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData' => [
                    'blueprint' => $this->supplyChainFields(),
                    'route' => [
                        'name' => 'grp.models.agent.store',
                    ],
                ],
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('supply-chain.edit');
    }

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation(app('group'), $request);

        return $request;
    }

    public function getBreadcrumbs($routeParameters): array
    {
        return array_merge(
            IndexAgents::make()->getBreadcrumbs('grp.supply-chain.agents.index', $routeParameters),
            [
                [
                    'type' => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('creating agent'),
                    ],
                ],
            ]
        );
    }
}

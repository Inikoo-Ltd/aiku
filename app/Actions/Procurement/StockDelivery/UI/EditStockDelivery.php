<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\StockDelivery\UI;

use App\Actions\OrgAction;
use App\Models\Procurement\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditStockDelivery extends OrgAction
{
    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        return $stockDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = true;

        // TODO:Raul Need to think of this
        return true;
    }

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->initialisation($organisation, $request);

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(StockDelivery $stockDelivery, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title' => __('supplier delivery'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $stockDelivery,
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title' => $stockDelivery->reference,
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name' => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],

                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title' => __('id'),
                            'fields' => [
                                'number' => [
                                    'type' => 'input',
                                    'label' => __('number'),
                                    'value' => $stockDelivery->number,
                                ],
                            ],
                        ],

                    ],
                    'args' => [
                        'updateRoute' => [
                            'name' => 'grp.models.supplier-delivery.update',
                            'parameters' => $stockDelivery->slug,

                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(StockDelivery $stockDelivery, array $routeParameters): array
    {
        return ShowStockDelivery::make()->getBreadcrumbs(
            stockDelivery: $stockDelivery,
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}

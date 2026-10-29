<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 10 May 2023 09:21:57 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\GoodsIn\StockDelivery\StoreAgentStockDelivery;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateStockDelivery extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithAgentOrganisation;

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New supplier delivery'),
                'pageHead'    => [
                    'title'        => __('New supplier delivery'),
                    'cancelCreate' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.stock_deliveries.index',
                            'parameters' => array_values($request->route()->originalParameters())
                        ],
                    ]

                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Create supplier delivery'),
                            'fields' => [

                                'number' => [
                                    'type'  => 'input',
                                    'label' => __('number'),
                                    'value' => ''
                                ],
                                'date' => [
                                    'type'  => 'date',
                                    'label' => __('Date'),
                                    'value' => ''
                                ],
                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.supplier-delivery.store',
                    ]
                ],


            ]
        );
    }


    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($request);
    }

    /**
     * An agent opens an empty shipment for one of the organisations it buys for.
     */
    public function inAgent(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->agentContainerForm($agent, $request);
    }

    private function agentContainerForm(Agent $agent, ActionRequest $request): Response
    {
        $organisationOptions = StoreAgentStockDelivery::orgAgentsOfAgent($agent)
            ->with('organisation:id,name,country_id', 'organisation.country:id,name')
            ->get()
            ->sortBy(fn (OrgAgent $orgAgent) => $orgAgent->organisation->name)
            ->map(fn (OrgAgent $orgAgent) => [
                'label' => $orgAgent->organisation->country
                    ? $orgAgent->organisation->name.' ('.$orgAgent->organisation->country->name.')'
                    : $orgAgent->organisation->name,
                'value' => $orgAgent->id,
            ])
            ->values();

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New shipment'),
                'pageHead'    => [
                    'title'   => __('New shipment'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-truck-container'],
                        'title' => __('Shipment'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.agent.stock_deliveries.current',
                                'parameters' => [$this->organisation->slug],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Shipment'),
                            'fields' => [
                                'org_agent_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Organisation'),
                                    'information' => __('The organisation the goods in this shipment are for. Only its confirmed purchase orders can be added to it.'),
                                    'required'    => true,
                                    'options'     => $organisationOptions->all(),
                                    'value'       => $organisationOptions->count() === 1 ? $organisationOptions->first()['value'] : null,
                                ],
                                'delivery_type' => [
                                    'type'        => 'select',
                                    'label'       => __('How it ships'),
                                    'information' => __('It can be changed later.'),
                                    'required'    => true,
                                    'options'     => StockDeliveryTypeEnum::selectOptions(),
                                    'value'       => StockDeliveryTypeEnum::usualFor($this->organisation)->value,
                                ],
                                'container_size' => [
                                    'type'        => 'select',
                                    'label'       => __('Container size'),
                                    'information' => __('It can be changed later.'),
                                    'required'    => true,
                                    'options'     => OrgAgentOrderContainerEnum::selectOptions(),
                                    'value'       => OrgAgentOrderContainerEnum::FORTY_FOOT->value,
                                    'showIf'      => ['field' => 'delivery_type', 'value' => StockDeliveryTypeEnum::CONTAINER->value],
                                ],
                                'reference'    => [
                                    'type'        => 'input',
                                    'label'       => __('Name'),
                                    'information' => __('Letters, numbers, dashes and underscores. Leave it empty to use the next reference of the organisation.'),
                                    'value'       => '',
                                ],
                            ],
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.org.agent.stock_deliveries.store',
                        'parameters' => [$this->organisation->slug],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexStockDeliveries::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => str_starts_with($routeName, 'grp.org.agent.') ? __('Creating shipment') : __('Creating stock delivery'),
                    ]
                ]
            ]
        );
    }
}

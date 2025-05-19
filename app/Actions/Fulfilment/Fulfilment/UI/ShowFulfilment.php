<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jan 2024 15:00:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithDashboard;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Fulfilment\FulfilmentTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilment extends OrgAction
{
    use WithDashboard;
    use WithFulfilmentShopAuthorisation;


    public function handle(Fulfilment $fulfilment): Fulfilment
    {
        return $fulfilment;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Fulfilment
    {

        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentTabsEnum::values());

        return $this->handle($fulfilment);
    }

    private function getDashboard(Fulfilment $fulfilment): array
    {
        $classApprovalVisual = 'text-black';

        if ($fulfilment->shop->crmStats->number_customers_status_pending_approval > 5) {
            $classApprovalVisual = 'text-red-500';
        } elseif ($fulfilment->shop->crmStats->number_customers_status_pending_approval == 0) {
            $classApprovalVisual = 'text-green-500';
        }

        return [


            'dashboard_stats'     => [
                'widgets' => [
                    'column_count' => 4,
                    'components'   => [

                        $this->getWidget(
                            data: [
                                'value'       => $fulfilment->shop->orderingStats->number_invoices,
                                'description' => __('invoices'),
                                'type'        => 'number',
                                'route'       => [
                                    'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                                    'parameters' => [
                                        $fulfilment->organisation->slug,
                                        $fulfilment->slug
                                    ]
                                ]
                            ],
                            visual: [
                                'label'       => __('Paid'),
                                'type'        => 'MeterGroup',
                                'value'       => $fulfilment->shop->orderingStats->number_invoices - $fulfilment->shop->orderingStats->number_unpaid_invoices,
                                'max'         => $fulfilment->shop->orderingStats->number_invoices,
                                'color'       => 'bg-blue-500',
                                'right_label' => [
                                    'label' => __('Unpaid').' '.$fulfilment->shop->orderingStats->number_unpaid_invoices,
                                    'route' => [
                                        'name'       => 'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.index',
                                        'parameters' => [
                                            $fulfilment->organisation->slug,
                                            $fulfilment->slug
                                        ]
                                    ]
                                ]


                            ],
                        ),


                        $this->getWidget(
                            data: [
                                'value'         => $fulfilment->stats->current_recurring_bills_amount,
                                'description'   => __('Next Bills'),
                                'type'          => 'currency',
                                'status'        => $fulfilment->stats->current_recurring_bills_amount < 0 ? 'danger' : '',
                                'currency_code' => $fulfilment->shop->currency->code,
                                'route'         => [
                                    'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.index',
                                    'parameters' => [
                                        $fulfilment->organisation->slug,
                                        $fulfilment->slug
                                    ]
                                ]
                            ],
                            visual: [
                                'label' => __('Bills'),
                                'type'  => 'number_with_label',
                                'value' => $fulfilment->stats->number_recurring_bills_status_current,
                                'route' => [
                                    'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.index',
                                    'parameters' => [
                                        $fulfilment->organisation->slug,
                                        $fulfilment->slug
                                    ]
                                ]
                            ],
                        ),

                        $this->getWidget(
                            data: [
                                'value'       => $fulfilment->stats->number_customers_status_active,
                                'description' => __('Active Customers'),
                                'type'        => 'number',
                                'route'       => [
                                    'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                                    'parameters' => [
                                        'organisation'     => $fulfilment->organisation->slug,
                                        'fulfilment'       => $fulfilment->slug,
                                        'elements[status]' => 'active'
                                    ]
                                ]
                            ],
                            visual: [
                                'label' => __('Pending Approval Customers'),
                                'type'  => 'number_with_label',
                                'class' => $classApprovalVisual,
                                'value' => $fulfilment->shop->crmStats->number_customers_status_pending_approval,
                                'route' => [
                                    'name'       => 'grp.org.fulfilments.show.crm.customers.pending_approval.index',
                                    'parameters' => [
                                        $fulfilment->organisation->slug,
                                        $fulfilment->slug
                                    ]
                                ]
                            ],
                        ),
                        $this->getWidget(
                            data: [
                                'value'       => $fulfilment->stats->number_pallet_deliveries_state_submitted,
                                'description' => __('Submitted Deliveries'),
                                'type'        => 'number',
                                'route'       => [
                                    'name'       => 'grp.org.fulfilments.show.operations.pallet-deliveries.index',
                                    'parameters' => [
                                        'organisation' => $fulfilment->organisation->slug,
                                        'fulfilment'   => $fulfilment->slug,
                                        'deliveries_elements[state]' => 'submitted'
                                    ]
                                ]
                            ],
                            visual: [
                                'label' => __('Submitted Returns'),
                                'type'  => 'number_with_label',
                                'value' => $fulfilment->stats->number_pallet_returns_state_submitted,
                                'route' => [
                                    'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.new.index',
                                    'parameters' => [
                                        'organisation' => $fulfilment->organisation->slug,
                                        'fulfilment'   => $fulfilment->slug,
                                        'returns_elements[state]' => 'submitted'
                                    ]
                                ]
                            ],
                        ),


                    ]
                ]
            ],
            'flatTreeMaps'        => [
                [

                    [
                        'name'  => __('Customers'),
                        'icon'  => ['fal', 'fa-user-tie'],
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                            'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        ],
                        'index' => [
                            'number' => $fulfilment->shop->crmStats->number_customers
                        ],

                    ],
                    [
                        'name'  => __('Recurring bills'),
                        'icon'  => ['fal', 'fa-receipt'],
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.index',
                            'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        ],
                        'index' => [
                            'number' => $fulfilment->stats->number_recurring_bills
                        ],
                    ],
                    [
                        'name'  => __('Invoices'),
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                            'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        ],
                        'index' => [
                            'number' => $fulfilment->shop->orderingStats->number_invoices
                        ],
                    ],


                ],
                [
                    [
                        'name'  => __('Pallets'),
                        'icon'  => ['fal', 'fa-pallet'],
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallets.current.index',
                            'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        ],
                        'index' => [
                            'number' => $this->organisation->fulfilmentStats->number_pallets_status_storing +
                                $this->organisation->fulfilmentStats->number_pallets_status_receiving +
                                $this->organisation->fulfilmentStats->number_pallets_status_returning
                        ],
                    ],
                    [
                        'name'  => __("Customer'S SKUs"),
                        'icon'  => ['fal', 'fa-narwhal'],
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallets.current.index',
                            'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        ],
                        'index' => [
                            'number' => $this->organisation->fulfilmentStats->number_stored_items
                        ],
                    ],

                ],
                [
                    [
                        'name'  => __('Deliveries'),
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallet-deliveries.index',
                            'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        ],
                        'index' => [
                            'number' => $fulfilment->stats->number_pallet_deliveries
                        ],
                    ],
                    [
                        'name'  => __('Returns'),
                        'icon'  => ['fal', 'fa-sign-out'],
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                            'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        ],
                        'index' => [
                            'number' => $this->organisation->fulfilmentStats->number_pallet_returns
                        ],
                    ],


                ],
            ],
            'scheduledActivities' => [
                [
                    'icon'        => 'fal fa-pallet',
                    'title'       => __('pallets  '),
                    'description' => (
                        $this->organisation->fulfilmentStats->number_pallets_state_in_process
                            + $this->organisation->fulfilmentStats->number_pallets_state_submitted
                            + $this->organisation->fulfilmentStats->number_pallets_state_confirmed
                    ).' '.__('pending')
                ],
                [
                    'icon'        => 'fal fa-truck-couch',
                    'title'       => __('pallet delivery'),
                    'description' => (
                        $this->organisation->fulfilmentStats->number_pallet_deliveries_state_in_process
                            + $this->organisation->fulfilmentStats->number_pallet_deliveries_state_submitted
                            + $this->organisation->fulfilmentStats->number_pallet_deliveries_state_confirmed
                    ).' '.__('pending')
                ],
                [
                    'icon'        => 'fal fa-sign-out',
                    'title'       => __('pallet returns'),
                    'description' => (
                        $this->organisation->fulfilmentStats->number_pallet_returns_state_in_process
                            + $this->organisation->fulfilmentStats->number_pallet_returns_state_submitted
                            + $this->organisation->fulfilmentStats->number_pallet_returns_state_confirmed
                            + $this->organisation->fulfilmentStats->number_pallet_returns_state_picking
                            + $this->organisation->fulfilmentStats->number_pallet_returns_state_picked
                    ).' '.__('pending')
                ],
            ]
        ];
    }

    public function htmlResponse(Fulfilment $fulfilment, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Fulfilment/Fulfilment',
            [
                'title'       => __('fulfilment'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($fulfilment, $request),
                    'next'     => $this->getNext($fulfilment, $request),
                ],
                'pageHead'    => [
                    'title' => $fulfilment->shop->name,
                    'icon'  => [
                        'title' => __('Fulfilment'),
                        'icon'  => 'fal fa-pallet-alt'
                    ],

                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentTabsEnum::navigation()
                ],

                FulfilmentTabsEnum::DASHBOARD->value => $this->tab == FulfilmentTabsEnum::DASHBOARD->value
                    ?
                    fn () => $this->getDashboard($fulfilment)
                    : Inertia::lazy(fn () => $this->getDashboard($fulfilment)),


            ]
        );
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->fillFromRequest($request);

        $this->set('canEdit', $request->user()->authTo('hr.edit'));
        $this->set('canViewUsers', $request->user()->authTo('users.view'));
    }

    public function jsonResponse(Fulfilment $fulfilment): FulfilmentResource
    {
        return new FulfilmentResource($fulfilment);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $fulfilment = Fulfilment::where('slug', Arr::get($routeParameters, 'fulfilment'))->first();

        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.fulfilments.index',
                                    'parameters' => Arr::only($routeParameters, 'organisation')
                                ],
                                'label' => __('Fulfilments'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => 'grp.org.fulfilments.show.operations.dashboard',
                                    'parameters' => $routeParameters
                                ],
                                'label' => $fulfilment?->shop?->name,
                                'icon'  => 'fal fa-bars'
                            ]

                        ],
                        'suffix'         => $suffix,
                    ]
                ]
            );
    }

    public function getPrevious(Fulfilment $fulfilment, ActionRequest $request): ?array
    {
        $previous = Shop::where('organisation_id', $this->organisation->id)->where('type', ShopTypeEnum::FULFILMENT)->where('code', '<', $fulfilment->shop->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous?->fulfilment, $request->route()->getName());
    }

    public function getNext(Fulfilment $fulfilment, ActionRequest $request): ?array
    {
        $next = Shop::where('organisation_id', $this->organisation->id)->where('type', ShopTypeEnum::FULFILMENT)->where('code', '>', $fulfilment->shop->code)->orderBy('code')->first();

        return $this->getNavigation($next?->fulfilment, $request->route()->getName());
    }

    private function getNavigation(?Fulfilment $fulfilment, string $routeName): ?array
    {
        if (!$fulfilment) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.operations.dashboard' => [
                'label' => $fulfilment->shop?->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'fulfilment'   => $fulfilment->slug
                    ]

                ]
            ]
        };
    }
}

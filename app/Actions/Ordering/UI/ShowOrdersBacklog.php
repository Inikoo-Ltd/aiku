<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:40:27 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Actions\Traits\WithTabsBox;
use App\Enums\UI\Ordering\OrdersBacklogTabsEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrdersBacklog extends OrgAction
{
    use WithTabsBox;
    use WithOrderingAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(OrdersBacklogTabsEnum::values());

        return $shop;
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request)->withTab(OrdersBacklogTabsEnum::values());

        return $organisation;
    }

    public function inGroup(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(group(), $request)->withTab(OrdersBacklogTabsEnum::values());

        return group();
    }

    public function htmlResponse(Group|Organisation|Shop $parent, ActionRequest $request): Response
    {
        $tabsBox = $this->getTabsBox($parent);

        return Inertia::render(
            'Ordering/OrdersBacklog',
            [
                'breadcrumbs' => $this->getBreadcrumbs($parent, $request->route()->originalParameters()),
                'title'       => __('Orders backlog'),
                'pageHead'    => [
                    'title' => __('Orders backlog'),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $tabsBox
                ],

                OrdersBacklogTabsEnum::IN_BASKET->value => $this->tab == OrdersBacklogTabsEnum::IN_BASKET->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))),

                OrdersBacklogTabsEnum::SUBMITTED_PAID->value => $this->tab == OrdersBacklogTabsEnum::SUBMITTED_PAID->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))),

                OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value => $this->tab == OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))),

                OrdersBacklogTabsEnum::IN_WAREHOUSE->value => $this->tab == OrdersBacklogTabsEnum::IN_WAREHOUSE->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))),

                OrdersBacklogTabsEnum::HANDLING->value => $this->tab == OrdersBacklogTabsEnum::HANDLING->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))),

                OrdersBacklogTabsEnum::HANDLING_BLOCKED->value => $this->tab == OrdersBacklogTabsEnum::HANDLING_BLOCKED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))),

                OrdersBacklogTabsEnum::PACKED->value => $this->tab == OrdersBacklogTabsEnum::PACKED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))),

                OrdersBacklogTabsEnum::FINALISED->value => $this->tab == OrdersBacklogTabsEnum::FINALISED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))),

                OrdersBacklogTabsEnum::DISPATCHED_TODAY->value => $this->tab == OrdersBacklogTabsEnum::DISPATCHED_TODAY->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))),
            ]
        )->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value));
    }

    public function getBreadcrumbs(Group|Organisation|Shop $parent, array $routeParameters): array
    {
        return match (class_basename($parent)) {
            'Shop' =>
            array_merge(
                ShowOrderingDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.ordering.backlog',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Orders backlog')
                        ]
                    ]
                ]
            ),
            'Organisation' =>
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.overview.ordering.backlog',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Orders backlog').' ('.__('all shops').')',
                        ]
                    ]
                ]
            ),
            'Group' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.overview.ordering.backlog',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Orders backlog').' ('.__('all organisations').')',
                        ]
                    ]
                ]
            )
        };
    }
}

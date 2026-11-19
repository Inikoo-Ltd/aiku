<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:40:27 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UI;

use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\GoodsIn\ReturnDeliveryNote\UI\IndexReturnDeliveryNotes;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Production\PartnerShippingList\GetOrdersBlockedByProduction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Actions\Traits\WithTabsBox;
use App\Enums\UI\Ordering\OrdersBacklogTabsEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\Http\Resources\Procurement\ReturnDeliveryNotesResource;
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
        $this->initialisationFromShop($shop, $request)->withTab(OrdersBacklogTabsEnum::values(), OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value);

        return $shop;
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request)->withTab(OrdersBacklogTabsEnum::values(), OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value);

        return $organisation;
    }

    public function inGroup(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(group(), $request)->withTab(OrdersBacklogTabsEnum::values(), OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value);

        return group();
    }

    public function htmlResponse(Group|Organisation|Shop $parent, ActionRequest $request): Response
    {
        $waitingItemsData = $this->buildWaitingItemsData($parent, $request);
        $tabsBox          = $this->getTabsBox($parent, $waitingItemsData);
        $tabsBox[]        = $this->getPreOrdersTabsBox($parent);

        return Inertia::render(
            'Ordering/OrdersBacklog',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($parent, $request->route()->originalParameters()),
                'title'        => __('Orders backlog'),
                'pageHead'     => [
                    'title' => __('Orders backlog'),
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => $tabsBox
                ],
                'backlog_filters' => $this->tab == OrdersBacklogTabsEnum::RETURNED->value ? null : $this->getBacklogFilters($parent, $request),
                'attention'       => IndexOrders::make()->backlogAttention($parent) + ['waiting_items_route' => $waitingItemsData['route']],
                'bankPaymentsNotice' => $parent instanceof Shop ? IndexBankIncoming::shopNotice($parent, $request->user()) : null,
                'productionBlockedNotice' => $parent instanceof Group ? null : GetOrdersBlockedByProduction::make()->notice($parent),
                'production_review_bulk_route' => !$parent instanceof Group && IndexOrders::make()->usesProductionReview($parent) ? [
                    'method'     => 'patch',
                    'name'       => 'grp.models.organisation.orders.production_review',
                    'parameters' => ['organisation' => $parent instanceof Shop ? $parent->organisation_id : $parent->id],
                ] : null,

                OrdersBacklogTabsEnum::IN_BASKET->value => $this->tab == OrdersBacklogTabsEnum::IN_BASKET->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))),

                OrdersBacklogTabsEnum::SUBMITTED_PAID->value => $this->tab == OrdersBacklogTabsEnum::SUBMITTED_PAID->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))),

                OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value => $this->tab == OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))),

                OrdersBacklogTabsEnum::IN_WAREHOUSE->value => $this->tab == OrdersBacklogTabsEnum::IN_WAREHOUSE->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))),

                OrdersBacklogTabsEnum::HANDLING->value => $this->tab == OrdersBacklogTabsEnum::HANDLING->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))),

                OrdersBacklogTabsEnum::HANDLING_BLOCKED->value => $this->tab == OrdersBacklogTabsEnum::HANDLING_BLOCKED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))),

                OrdersBacklogTabsEnum::PICKED->value => $this->tab == OrdersBacklogTabsEnum::PICKED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PICKED->value, bucket: OrdersBacklogTabsEnum::PICKED->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PICKED->value, bucket: OrdersBacklogTabsEnum::PICKED->value))),

                OrdersBacklogTabsEnum::PACKING->value => $this->tab == OrdersBacklogTabsEnum::PACKING->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKING->value, bucket: OrdersBacklogTabsEnum::PACKING->value))
                : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKING->value, bucket: OrdersBacklogTabsEnum::PACKING->value))),

                OrdersBacklogTabsEnum::PACKED->value => $this->tab == OrdersBacklogTabsEnum::PACKED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))),

                OrdersBacklogTabsEnum::FINALISED->value => $this->tab == OrdersBacklogTabsEnum::FINALISED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))),

                OrdersBacklogTabsEnum::DISPATCHED_TODAY->value => $this->tab == OrdersBacklogTabsEnum::DISPATCHED_TODAY->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))),

                OrdersBacklogTabsEnum::PRE_ORDERS->value => $this->tab == OrdersBacklogTabsEnum::PRE_ORDERS->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PRE_ORDERS->value, bucket: OrdersBacklogTabsEnum::PRE_ORDERS->value))
                    : Inertia::optional(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PRE_ORDERS->value, bucket: OrdersBacklogTabsEnum::PRE_ORDERS->value))),

                OrdersBacklogTabsEnum::RETURNED->value => $this->tab == OrdersBacklogTabsEnum::RETURNED->value ?
                    fn () => ReturnDeliveryNotesResource::collection(IndexReturnDeliveryNotes::run(parent: $parent, prefix: OrdersBacklogTabsEnum::RETURNED->value, bucket: OrdersBacklogTabsEnum::RETURNED->value))
                    : Inertia::optional(fn () => ReturnDeliveryNotesResource::collection(IndexReturnDeliveryNotes::run(parent: $parent, prefix: OrdersBacklogTabsEnum::RETURNED->value, bucket: OrdersBacklogTabsEnum::RETURNED->value))),
            ]
        )
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))

            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::PICKED->value, bucket: OrdersBacklogTabsEnum::PICKED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKING->value, bucket: OrdersBacklogTabsEnum::PACKING->value))


            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))

            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::PRE_ORDERS->value, bucket: OrdersBacklogTabsEnum::PRE_ORDERS->value))
            ->table(IndexReturnDeliveryNotes::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::RETURNED->value, bucket: OrdersBacklogTabsEnum::RETURNED->value));
    }

    /**
     * @return array{prefix: string, current: array{scope: ?string, channel: ?string, production_review?: ?string, attention?: ?string}, counts: array<string, array<string, int>>}
     */
    protected function getBacklogFilters(Group|Organisation|Shop $parent, ActionRequest $request): array
    {
        $allowed = [
            'scope'   => ['domestic', 'export'],
            'channel' => ['direct', 'partner'],
        ];
        if (IndexOrders::make()->usesProductionReview($parent)) {
            $allowed['production_review'] = ['reviewed', 'unreviewed'];
        }
        $showsStage = IndexOrders::make()->showsStage($this->tab);
        if ($showsStage) {
            $allowed['attention'] = ['stuck', 'waiting_stock', 'waiting_cs'];
        }
        $splitsPayment = !in_array($this->tab, [OrdersBacklogTabsEnum::IN_BASKET->value, OrdersBacklogTabsEnum::SUBMITTED_PAID->value, OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value]);
        if ($splitsPayment) {
            $allowed['payment'] = ['paid', 'unpaid'];
        }

        $current = [];
        foreach ($allowed as $key => $values) {
            $value         = $request->input($this->tab.'_elements.'.$key);
            $current[$key] = in_array($value, $values) ? $value : null;
        }

        $counts = IndexOrders::make()->backlogFilterCounts($parent, $this->tab, $current);
        if (!$splitsPayment) {
            unset($counts['payment']);
        }
        if ($showsStage) {
            $counts['attention'] = Arr::only(IndexOrders::make()->backlogAttentionCounts($parent, $this->tab, $current), $allowed['attention']);
        }

        return [
            'prefix'  => $this->tab,
            'current' => $current,
            'counts'  => $counts,
        ];
    }

    /**
     * Orders holding a pre-order, waiting for the supplier's goods or already released and not yet dispatched.
     */
    protected function getPreOrdersTabsBox(Group|Organisation|Shop $parent): array
    {
        $icon = [
            'tooltip' => __('Pre-orders'),
            'icon'    => 'fal fa-hourglass-half',
            'class'   => 'text-gray-500',
            'color'   => 'gray',
            'app'     => [
                'name' => 'hourglass-half',
                'type' => 'font-awesome-5'
            ]
        ];

        $children = match (true) {
            $parent instanceof Group        => $parent->organisations,
            $parent instanceof Organisation => $parent->shops,
            default                         => collect(),
        };

        $counts = IndexOrders::make()->livePreOrderCounts($parent, $parent instanceof Group ? 'orders.organisation_id' : 'orders.shop_id');

        return [
            'label'         => __('Pre-orders'),
            'currency_code' => $parent->currency->code,
            'tabs'          => [
                [
                    'tab_slug'  => OrdersBacklogTabsEnum::PRE_ORDERS->value,
                    'label'     => __('Pre-orders'),
                    'value'     => array_sum($counts),
                    'icon_data' => $icon,
                    'type'      => 'number',
                ],
            ],
            'children'      => $children->map(fn ($child) => [
                'label'         => $child->name,
                'slug'          => $child->slug,
                'currency_code' => $child->currency?->code ?? $parent->currency->code,
                'tabs'          => [
                    [
                        'tab_slug' => OrdersBacklogTabsEnum::PRE_ORDERS->value,
                        'value'    => $counts[$child->id] ?? 0,
                        'type'     => 'number',
                        'route'    => $this->getChildRoute($parent, $child, OrdersBacklogTabsEnum::PRE_ORDERS->value),
                    ],
                ]
            ])->values()->toArray(),
        ];
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

<?php

/*
 * author Louis Perez
 * created on 20-04-2026-09h-11m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Fulfilment\WithPalletReturnSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\PalletReturnsBacklogTabsEnum;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPalletReturnsBacklog extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithPalletReturnSubNavigation;
    use WithFulfilmentShopAuthorisation;

    private Fulfilment $parent;
    private PalletReturnTypeEnum $typeScope;

    public function inDropshipping(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request)
    {
        $this->typeScope   = PalletReturnTypeEnum::STORED_ITEM;
        $this->parent      = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsBacklogTabsEnum::values());

        return $this->parent;
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request)
    {
        $this->typeScope   = PalletReturnTypeEnum::PALLET;
        $this->parent      = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnsBacklogTabsEnum::values());

        return $this->parent;
    }

    public function htmlResponse(Fulfilment $parent, ActionRequest $request): Response
    {
        $tabsBox    = $this->getTabsBox($parent);

        $icon       = ['fal', 'fa-sign-out-alt'];
        $title      = __('Returns Backlog') . " ({$this->typeScope->labelsNew()})";
        $afterTitle = null;
        $iconRight  = null;
        $model      = null;

        $actions = [];


        if ($this->parent->number_pallets_status_storing) {
            $actions[] = [
                'type'        => 'button',
                'style'       => 'create',
                'tooltip'     => $this->parent->items_storage ? __('Create new return (whole pallet)') : __('Create new return'),
                'label'       => $this->parent->items_storage ? __('Return (whole pallet)') : __('Return'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.store',
                    'parameters' => [$this->parent->id]
                ]
            ];
        }
        if ($this->parent->items_storage && $this->parent->number_pallets_with_stored_items_state_storing > 0) {
            $actions[] = [
                'type'        => 'button',
                'style'       => 'create',
                'tooltip'     => __('Create new return (Customer SKUs)'),
                'label'       => __('Return (Customer SKUs)'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return-stored-items.store',
                    'parameters' => [$this->parent->id]
                ]
            ];
        }

        return Inertia::render(
            'Org/Fulfilment/PalletReturnsBacklog',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $this->typeScope
                ),
                'title'       => __('Pallet Return Backlog'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'actions'       => $actions
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $tabsBox
                ],

                PalletReturnsBacklogTabsEnum::IN_PROCESS->value => $this->tab == PalletReturnsBacklogTabsEnum::IN_PROCESS->value ?
                    fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::IN_PROCESS, $this->typeScope, PalletReturnStateEnum::IN_PROCESS->value))
                    : Inertia::lazy(fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::IN_PROCESS, $this->typeScope, PalletReturnStateEnum::IN_PROCESS->value))),

                PalletReturnsBacklogTabsEnum::SUBMITTED->value => $this->tab == PalletReturnsBacklogTabsEnum::SUBMITTED->value ?
                    fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::SUBMITTED, $this->typeScope, PalletReturnStateEnum::SUBMITTED->value))
                    : Inertia::lazy(fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::SUBMITTED, $this->typeScope, PalletReturnStateEnum::SUBMITTED->value))),

                PalletReturnsBacklogTabsEnum::CONFIRMED->value => $this->tab == PalletReturnsBacklogTabsEnum::CONFIRMED->value ?
                    fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::CONFIRMED, $this->typeScope, PalletReturnStateEnum::CONFIRMED->value))
                    : Inertia::lazy(fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::CONFIRMED, $this->typeScope, PalletReturnStateEnum::CONFIRMED->value))),

                PalletReturnsBacklogTabsEnum::PICKING->value => $this->tab == PalletReturnsBacklogTabsEnum::PICKING->value ?
                    fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::PICKING, $this->typeScope, PalletReturnStateEnum::PICKING->value))
                    : Inertia::lazy(fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::PICKING, $this->typeScope, PalletReturnStateEnum::PICKING->value))),

                PalletReturnsBacklogTabsEnum::PICKED->value => $this->tab == PalletReturnsBacklogTabsEnum::PICKED->value ?
                    fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::PICKED, $this->typeScope, PalletReturnStateEnum::PICKED->value))
                    : Inertia::lazy(fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::PICKED, $this->typeScope, PalletReturnStateEnum::PICKED->value))),

                PalletReturnsBacklogTabsEnum::WAITING->value => $this->tab == PalletReturnsBacklogTabsEnum::WAITING->value ?
                    fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::PICKING, $this->typeScope, PalletReturnsBacklogTabsEnum::WAITING->value))
                    : Inertia::lazy(fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::PICKING, $this->typeScope, PalletReturnsBacklogTabsEnum::WAITING->value))),

                PalletReturnsBacklogTabsEnum::DISPATCHED->value => $this->tab == PalletReturnsBacklogTabsEnum::DISPATCHED->value ?
                    fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::DISPATCHED, $this->typeScope, PalletReturnStateEnum::DISPATCHED->value))
                    : Inertia::lazy(fn () => PalletReturnsResource::collection(IndexPalletReturnsBacklog::run($parent, PalletReturnStateEnum::DISPATCHED, $this->typeScope, PalletReturnStateEnum::DISPATCHED->value))),

            ]
        )
        ->table(IndexPalletReturnsBacklog::make()->tableStructure(parent: $this->parent, typeFilter: $this->typeScope, prefix: PalletReturnsBacklogTabsEnum::IN_PROCESS->value))
        ->table(IndexPalletReturnsBacklog::make()->tableStructure(parent: $this->parent, typeFilter: $this->typeScope, prefix: PalletReturnsBacklogTabsEnum::SUBMITTED->value))
        ->table(IndexPalletReturnsBacklog::make()->tableStructure(parent: $this->parent, typeFilter: $this->typeScope, prefix: PalletReturnsBacklogTabsEnum::CONFIRMED->value))
        ->table(IndexPalletReturnsBacklog::make()->tableStructure(parent: $this->parent, typeFilter: $this->typeScope, prefix: PalletReturnsBacklogTabsEnum::PICKING->value))
        ->table(IndexPalletReturnsBacklog::make()->tableStructure(parent: $this->parent, typeFilter: $this->typeScope, prefix: PalletReturnsBacklogTabsEnum::PICKED->value))
        ->table(IndexPalletReturnsBacklog::make()->tableStructure(parent: $this->parent, typeFilter: $this->typeScope, prefix: PalletReturnsBacklogTabsEnum::WAITING->value))
        ->table(IndexPalletReturnsBacklog::make()->tableStructure(parent: $this->parent, typeFilter: $this->typeScope, prefix: PalletReturnsBacklogTabsEnum::DISPATCHED->value));
    }

    public function getTabsBox(Fulfilment $parent): array
    {
        $statAccessor = 'pallet_returns_pallet';
        if ($this->typeScope == PalletReturnTypeEnum::STORED_ITEM)  {
            $statAccessor   = 'pallet_returns_items';
        }
        return [
            [
                'label'         => __('In Process'),
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_process',
                        'label'       => __('In Process'),
                        'value'       => $parent->stats->{"number_{$statAccessor}_state_in_process"} ?? 0,
                        'type'        => 'number',
                        'icon_data'   => [
                            'icon'    => 'fal fa-seedling',
                            'tooltip' => __('In Process'),
                        ],
                    ]
                ],
            ],
            [
                'label'         => __('Processed'),
                'tabs'          => [
                    [
                        'tab_slug'    => 'submitted',
                        'label'       => __('Submitted'),
                        'value'       => $parent->stats->{"number_{$statAccessor}_state_submitted"} ?? 0,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Submitted'),
                            'icon'    => 'fal fa-share',
                            'class'   => 'text-indigo-400',
                        ],
                    ],
                    [
                        'tab_slug'    => 'confirmed',
                        'label'       => __('Confirmed'),
                        'value'       => $parent->stats->{"number_{$statAccessor}_state_confirmed"} ?? 0,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Confirmed'),
                            'icon'    => 'fal fa-spell-check',
                            'class'   => 'text-emerald-500',
                        ],
                    ],
                ],
            ],
            [
                'label'         => __('In Warehouse'),
                'tabs'          => [
                    [
                        'tab_slug'    => 'picking',
                        'label'       => __('Picking'),
                        'value'       => $parent->stats->{"number_{$statAccessor}_state_picking"} ?? 0,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Picking'),
                            'icon'    => 'fal fa-truck',
                            'class'   => 'text-orange-500',
                        ],
                    ],
                    [
                        'tab_slug'    => 'waiting',
                        'label'       => __('Waiting'),
                        'value'       => PalletReturn::query()
                            ->where('fulfilment_id', $parent->id)
                            ->where('type', $this->typeScope->value)
                            ->where('state', PalletReturnStateEnum::PICKING->value)
                            ->where('number_items_waiting_crm', '>', 0)
                            ->count(),
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Waiting'),
                            'icon'    => 'fal fa-hourglass-start',
                            'class'   => 'text-amber-500',
                        ],
                    ],
                    [
                        'tab_slug'    => 'picked',
                        'label'       => __('Picked'),
                        'value'       => $parent->stats->{"number_{$statAccessor}_state_picked"} ?? 0,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Picked'),
                            'icon'    => 'fal fa-check',
                            'class'   => 'text-slate-500',
                        ],
                    ],
                ],
            ],
            [
                'label'         => __('Dispatched'),
                'tabs'          => [
                    [
                        'tab_slug'    => 'dispatched',
                        'label'       => __('Dispatched'),
                        'value'       => $parent->stats->{"number_{$statAccessor}_state_dispatched"} ?? 0,
                        'icon_data'   => [
                            'tooltip' => __('Dispatched'),
                            'icon'    => 'fal fa-check-double',
                            'class'   => 'text-purple-500',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, PalletReturnTypeEnum $typeScope): array
    {
        $headCrumb = function (array $routeParameters = []) use ($typeScope) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route'     => $routeParameters,
                        'label'     => __('Returns Backlog'),
                        'icon'      => 'fal fa-bars',
                    ],
                    'suffix'    => "({$typeScope->labelsNew()})"
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.fulfilments.show.backlogs.pallet-returns-backlog.dropship.index',
            'grp.org.fulfilments.show.backlogs.pallet-returns-backlog.wholesale.index' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                    ]
                )
            ),
        };
    }
}

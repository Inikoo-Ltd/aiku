<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jan 2024 14:58:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Enums\UI\CRM\ProspectsTabsEnum;
use App\Http\Resources\CRM\ProspectsResource;
use App\Http\Resources\History\HistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProspects extends OrgAction
{
    use WithProspectsSubNavigation;
    use WithCRMAuthorisation;

    private Group|Shop|Organisation|Fulfilment $parent;
    private string $scope;

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->tab    = ProspectsTabsEnum::PROSPECTS->value;
        $this->parent = group();
        $this->scope  = 'all';
        $tabs         = ProspectsTabsEnum::values();
        unset($tabs[array_search(ProspectsTabsEnum::DASHBOARD->value, $tabs)]);
        $this->initialisationFromGroup(group(), $request)->withTab($tabs);

        return $this->handle($this->parent, ProspectsTabsEnum::PROSPECTS->value, 'all');
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->scope  = 'all';
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(ProspectsTabsEnum::values());

        return $this->handle($this->parent, ProspectsTabsEnum::PROSPECTS->value, 'all');
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->scope  = 'all';
        $this->initialisationFromShop($shop, $request)->withTab(ProspectsTabsEnum::values());

        return $this->handle($shop, ProspectsTabsEnum::PROSPECTS->value, 'all');
    }

    protected function getElementGroups($parent, $scope): array
    {
        if ($scope == 'contacted') {
            $elements = [
                'contacted_state' => [
                    'label'    => __('Contacted State'),
                    'elements' => array_merge_recursive(
                        ProspectContactedStateEnum::labels(),
                        ProspectContactedStateEnum::count($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('prospects.contacted_state', $elements);
                    }
                ],
            ];
        } elseif ($scope == 'fail') {
            $elements = [
                'fail_status' => [
                    'label'    => __('Fail Status'),
                    'elements' => array_merge_recursive(
                        ProspectFailStatusEnum::labels(),
                        ProspectFailStatusEnum::count($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('prospects.fail_status', $elements);
                    }
                ],
            ];
        } elseif ($scope == 'success') {
            $elements = [
                'success_status' => [
                    'label'    => __('Success Status'),
                    'elements' => array_merge_recursive(
                        ProspectSuccessStatusEnum::labels(),
                        ProspectSuccessStatusEnum::count($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('prospects.success_status', $elements);
                    }
                ],
            ];
        } else {
            $elements = [
                'state' => [
                    'label'    => __('State'),
                    'elements' => array_merge_recursive(
                        ProspectStateEnum::labels(),
                        ProspectStateEnum::count($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('prospects.state', $elements);
                    }
                ]
            ];
        }

        return $elements;
    }

    // if $scope is set as $scope= all, then php artisan ide-helper:meta will fail!
    public function handle(Group|Organisation|Shop|Fulfilment|Tag $parent, $prefix = null, $scope): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('prospects.name', $value)
                    ->orWhereWith('prospects.email', $value)
                    ->orWhereWith('prospects.phone', $value)
                    ->orWhereWith('prospects.contact_website', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Prospect::class);

        if ($parent instanceof Organisation || $parent instanceof Shop) {
            foreach ($this->getElementGroups($parent, $scope) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        if ($parent instanceof Shop) {
            $queryBuilder->where('prospects.shop_id', $parent->id);
        } elseif ($parent instanceof Fulfilment) {
            $queryBuilder->where('prospects.shop_id', $parent->shop_id);
        } elseif ($parent instanceof Organisation) {
            $queryBuilder->where('prospects.organisation_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('prospects.group_id', $parent->id);
        }

        if ($scope == 'contacted') {
            $queryBuilder->where('prospects.state', ProspectStateEnum::CONTACTED);
        } elseif ($scope == 'fail') {
            $queryBuilder->where('prospects.state', ProspectStateEnum::FAIL);
        } elseif ($scope == 'success') {
            $queryBuilder->where('prospects.state', ProspectStateEnum::SUCCESS);
        }

        return $queryBuilder
            ->defaultSort('prospects.name')
            ->allowedSorts(['name', 'email', 'phone', 'contact_website'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation|Shop|Fulfilment|Tag $parent, ?array $modelOperations = null, $prefix = null, $scope = 'all'): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $scope) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            if (class_basename($parent) != 'Tag' && !($parent instanceof Group)) {
                foreach ($this->getElementGroups($parent, $scope) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch();
            if (!($parent instanceof Group)) {
                $table->withEmptyState(
                    [
                        'title'       => __('No Prospects'),
                        'description' => null,
                        'count'       => $parent->crmStats->number_prospects
                    ]
                );
            }
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'is_opt_in', label: __('Opt In'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'phone', label: __('phone'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'website', label: __('website'), canBeHidden: false, sortable: true, searchable: true);


        };
    }

    public function jsonResponse(LengthAwarePaginator $prospects): AnonymousResourceCollection
    {
        return ProspectsResource::collection($prospects);
    }


    public function htmlResponse(LengthAwarePaginator $prospects, ActionRequest $request): Response
    {
        $navigation = ProspectsTabsEnum::navigation();
        if (!($this->parent instanceof Shop)) {
            unset($navigation[ProspectsTabsEnum::CONTACTED->value]);
            unset($navigation[ProspectsTabsEnum::FAILED->value]);
            unset($navigation[ProspectsTabsEnum::SUCCESS->value]);
        }

        if ($this->parent instanceof Shop) {
            $spreadsheetRoute = [
                'event'           => 'action-progress',
                'channel'         => 'grp.personal.'.$this->group->id,
                'required_fields' => ["id:prospect_key", "company", "contact_name", "email", "telephone"],
                'route'           => [
                    'upload' => [
                        'name'       => 'grp.models.shop.prospects.upload',
                        'parameters' => [
                            'shop' => $this->parent->id
                        ]
                    ],
                ],
            ];
        }
        $subNavigation = $this->getSubNavigation($request);
        $dataProspect  = [
            'data' => $this->tab == ProspectsTabsEnum::PROSPECTS->value
                ? ProspectsResource::collection($prospects)
                : Inertia::lazy(fn () => ProspectsResource::collection($prospects)),

        ];

        $tabs = [
            'tabs' => [
                'current'    => $this->tab,
                'navigation' => $navigation,
            ],

            ProspectsTabsEnum::DASHBOARD->value => $this->tab == ProspectsTabsEnum::DASHBOARD->value ?
                fn () => GetProspectsDashboard::run($this->parent, $request)
                : Inertia::lazy(fn () => GetProspectsDashboard::run($this->parent, $request)),
            ProspectsTabsEnum::PROSPECTS->value => $this->tab == ProspectsTabsEnum::PROSPECTS->value ?
                fn () => $dataProspect
                : Inertia::lazy(fn () => $dataProspect),

            ProspectsTabsEnum::CONTACTED->value => $this->tab == ProspectsTabsEnum::CONTACTED->value ?
                fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::CONTACTED->value, scope: 'contacted'))
                : Inertia::lazy(fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::CONTACTED->value, scope: 'contacted'))),

            ProspectsTabsEnum::FAILED->value => $this->tab == ProspectsTabsEnum::FAILED->value ?
                fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::FAILED->value, scope: 'fail'))
                : Inertia::lazy(fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::FAILED->value, scope: 'fail'))),

            ProspectsTabsEnum::SUCCESS->value => $this->tab == ProspectsTabsEnum::SUCCESS->value ?
                fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::SUCCESS->value, scope: 'success'))
                : Inertia::lazy(fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::SUCCESS->value, scope: 'success'))),

            ProspectsTabsEnum::HISTORY->value => $this->tab == ProspectsTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run(model: Prospect::class, prefix: ProspectsTabsEnum::HISTORY->value))
                : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run(model: Prospect::class, prefix: ProspectsTabsEnum::HISTORY->value))),
        ];

        if ($this->parent instanceof Group) {
            $subNavigation = null;
            $tabs          = [
                'tabs'                              => [
                    'current'    => $this->tab,
                    'navigation' => Arr::except(ProspectsTabsEnum::navigation(), [ProspectsTabsEnum::DASHBOARD->value]),
                ],
                ProspectsTabsEnum::PROSPECTS->value => $this->tab == ProspectsTabsEnum::PROSPECTS->value ?
                    fn () => $dataProspect
                    : Inertia::lazy(fn () => $dataProspect),
            ];
        }


        return Inertia::render(
            'Org/Shop/CRM/Prospects',
            [
                'breadcrumbs'        => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'              => __('prospects'),
                'pageHead'           => array_filter([
                    'icon'          => ['fal', 'fa-user-plus'],
                    'title'         => __('prospects'),
                    'actions'       => [
                        $this->canEdit ? [
                            'type'    => 'buttonGroup',
                            'buttons' =>
                                match (class_basename($this->parent)) {
                                    'Shop' => [
                                        [
                                            'style' => 'primary',
                                            'icon'  => ['fal', 'fa-upload'],
                                            'label' => 'upload',
                                            'route' => [
                                                'name'       => 'grp.org.models.shop.prospects.upload',
                                                'parameters' => $this->parent->id

                                            ],
                                        ],
                                        [
                                            'type'  => 'button',
                                            'style' => 'create',
                                            'label' => __('prospect'),
                                            'route' => [
                                                'name'       => 'grp.org.shops.show.prospects.create',
                                                'parameters' => $request->route()->originalParameters()
                                            ]
                                        ]
                                    ],
                                    default => []
                                }


                        ] : false
                    ],
                    'subNavigation' => $subNavigation,
                ]),
                'uploads'            => [
                    'templates' => [
                        'routes' => [
                            'name' => 'org.downloads.templates.prospects'
                        ]
                    ],
                    'event'     => class_basename(Prospect::class),
                    'channel'   => 'uploads.org.'.request()->user()->id
                ],
                'upload_spreadsheet' => $spreadsheetRoute ?? null,
                'uploadRoutes'       => [
                    'upload'  => [
                        'name'       => 'org.models.shop.prospects.upload',
                        'parameters' => $this->parent->id
                    ],
                    'history' => [
                        'name'       => 'org.crm.prospects.uploads.history',
                        'parameters' => []
                    ],
                ],
                ...$tabs

            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: ProspectsTabsEnum::PROSPECTS->value))
            ->table($this->tableStructure(parent: $this->parent, prefix: ProspectsTabsEnum::CONTACTED->value, scope: 'contacted'))
            ->table($this->tableStructure(parent: $this->parent, prefix: ProspectsTabsEnum::FAILED->value, scope: 'fail'))
            ->table($this->tableStructure(parent: $this->parent, prefix: ProspectsTabsEnum::SUCCESS->value, scope: 'success'))
            ->table(IndexHistory::make()->tableStructure(prefix: ProspectsTabsEnum::HISTORY->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Prospects'),
                        'icon'  => 'fal fa-transporter'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.prospects.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.prospects.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.crm.prospects.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.overview.crm.prospects.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),

            default => []
        };
    }
}

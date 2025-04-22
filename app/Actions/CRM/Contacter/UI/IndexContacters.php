<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Contacter\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\UI\CRM\ProspectsTabsEnum;
use App\Http\Resources\CRM\ProspectsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Tag\TagResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Contacter;
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

class IndexContacters extends OrgAction
{
    use WithProspectsSubNavigation;
    use WithCRMAuthorisation;

    private Group|Shop|Organisation|Fulfilment $parent;
    private string $scope;

    // /** @noinspection PhpUnusedParameterInspection */
    // public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->parent = $fulfilment;
    //     $this->scope = 'all';
    //     $this->initialisationFromFulfilment($fulfilment, $request)->withTab(ProspectsTabsEnum::values());

    //     return $this->handle($this->parent, ProspectsTabsEnum::PROSPECTS->value, 'all');
    // }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        // $this->scope = 'all';
        $this->initialisationFromShop($shop, $request)->withTab(ProspectsTabsEnum::values());

        return $this->handle($shop, ProspectsTabsEnum::PROSPECTS->value, 'all');
    }

    public function handle(Group|Organisation|Shop|Fulfilment|Tag $parent, $prefix = null, $scope): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('contacters.name', $value)
                    ->orWhereWith('contacters.email', $value)
                    ->orWhereWith('contacters.phone', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Contacter::class);

        if ($parent instanceof Shop) {
            $queryBuilder->where('contacters.shop_id', $parent->id);
        } elseif ($parent instanceof Fulfilment) {
            $queryBuilder->where('contacters.shop_id', $parent->shop_id);
        } elseif ($parent instanceof Organisation) {
            $queryBuilder->where('contacters.organisation_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('contacters.group_id', $parent->id);
        } elseif ($parent instanceof Tag) {
            $queryBuilder->leftJoin('taggables', 'taggables.tag_id', '=', 'tags.id')
                 ->where('taggables.taggable_id', $parent->id)
                 ->where('taggables.taggable_type', 'Prospect');
        }

        return $queryBuilder
            ->defaultSort('contacters.name')
            ->allowedSorts(['name', 'email', 'phone', 'message'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation|Shop|Fulfilment|Tag $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch();
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
            ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
            ->column(key: 'email', label: __('email'), canBeHidden: false, sortable: true, searchable: true)
            ->column(key: 'phone', label: __('phone'), canBeHidden: false, sortable: true, searchable: true)
            ->column(key: 'message', label: __('message'), canBeHidden: false, sortable: true, searchable: true);

            // if (class_basename($parent) != 'Tag') {
            //     $table->column(key: 'tags', label: __('tags'), canBeHidden: false, sortable: true, searchable: true);
            // }
        };
    }

    public function jsonResponse(LengthAwarePaginator $prospects): AnonymousResourceCollection
    {
        return ProspectsResource::collection($prospects);
    }


    public function htmlResponse(LengthAwarePaginator $contacters, ActionRequest $request): Response
    {
        // $navigation = ProspectsTabsEnum::navigation();
        // if (!($this->parent instanceof Shop)) {
        //     unset($navigation[ProspectsTabsEnum::CONTACTED->value]);
        //     unset($navigation[ProspectsTabsEnum::FAILED->value]);
        //     unset($navigation[ProspectsTabsEnum::SUCCESS->value]);
        // }

        // if ($this->parent instanceof Shop) {
        //     $spreadsheetRoute = [
        //         'event'           => 'action-progress',
        //         'channel'         => 'grp.personal.'.$this->group->id,
        //         'required_fields' => ["id:prospect_key", "company", "contact_name", "email", "telephone"],
        //         'route'           => [
        //             'upload'   => [
        //                 'name'       => 'grp.models.shop.prospects.upload',
        //                 'parameters' => [
        //                     'shop' => $this->parent->id
        //                 ]
        //             ],
        //         ],
        //     ];
        // }
        // $subNavigation = $this->getSubNavigation($request);
        // $dataProspect  = [
        //     'data' => $this->tab == ProspectsTabsEnum::PROSPECTS->value
        //         ? ProspectsResource::collection($prospects)
        //         : Inertia::lazy(fn () => ProspectsResource::collection($prospects)),

        //     'tagRoute' => [
        //         'store'  => [
        //             'name'       => 'grp.models.prospect.tag.store',
        //             'parameters' => [],
        //         ],
        //         'update' => [
        //             'name'       => 'grp.models.prospect.tag.attach',
        //             'parameters' => [],
        //         ],
        //     ],

        //     'tagsList' => TagResource::collection(Tag::where('type', 'crm')->get()),
        // ];

        // $tabs = [
        //     'tabs' => [
        //         'current'    => $this->tab,
        //         'navigation' => $navigation,
        //     ],

        //     ProspectsTabsEnum::DASHBOARD->value => $this->tab == ProspectsTabsEnum::DASHBOARD->value ?
        //         fn () => GetProspectsDashboard::run($this->parent, $request)
        //         : Inertia::lazy(fn () => GetProspectsDashboard::run($this->parent, $request)),
        //     ProspectsTabsEnum::PROSPECTS->value => $this->tab == ProspectsTabsEnum::PROSPECTS->value ?
        //         fn () => $dataProspect
        //         : Inertia::lazy(fn () => $dataProspect),

        //     ProspectsTabsEnum::CONTACTED->value   => $this->tab == ProspectsTabsEnum::CONTACTED->value ?
        //         fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::CONTACTED->value, scope: 'contacted'))
        //         : Inertia::lazy(fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::CONTACTED->value, scope: 'contacted'))),

        //     ProspectsTabsEnum::FAILED->value   => $this->tab == ProspectsTabsEnum::FAILED->value ?
        //         fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::FAILED->value, scope: 'fail'))
        //         : Inertia::lazy(fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::FAILED->value, scope: 'fail'))),

        //     ProspectsTabsEnum::SUCCESS->value   => $this->tab == ProspectsTabsEnum::SUCCESS->value ?
        //         fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::SUCCESS->value, scope: 'success'))
        //         : Inertia::lazy(fn () => ProspectsResource::collection(IndexProspects::run(parent: $this->parent, prefix: ProspectsTabsEnum::SUCCESS->value, scope: 'success'))),

        //     ProspectsTabsEnum::HISTORY->value   => $this->tab == ProspectsTabsEnum::HISTORY->value ?
        //         fn () => HistoryResource::collection(IndexHistory::run(model: Prospect::class, prefix: ProspectsTabsEnum::HISTORY->value))
        //         : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run(model: Prospect::class, prefix: ProspectsTabsEnum::HISTORY->value))),
        // ];

        // if ($this->parent instanceof Group) {
        //     $subNavigation = null;
        //     $tabs          = [
        //         'tabs'                              => [
        //             'current'    => $this->tab,
        //             'navigation' => Arr::except(ProspectsTabsEnum::navigation(), [ProspectsTabsEnum::DASHBOARD->value]),
        //         ],
        //         ProspectsTabsEnum::PROSPECTS->value => $this->tab == ProspectsTabsEnum::PROSPECTS->value ?
        //             fn () => $dataProspect
        //             : Inertia::lazy(fn () => $dataProspect),
        //     ];
        // }


        return Inertia::render(
            'Org/Shop/CRM/Contacters',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'        => __('contacters'),
                'pageHead'     => array_filter([
                    'icon'          => ['fal', 'fa-user-plus'],
                    'title'         => __('contacters'),
                    'actions'       => [
                        $this->canEdit ? [
                            'type'    => 'buttonGroup',
                            'buttons' =>
                                match (class_basename($this->parent)) {
                                    // 'Shop' => [
                                    //     [
                                    //         'style' => 'primary',
                                    //         'icon'  => ['fal', 'fa-upload'],
                                    //         'label' => 'upload',
                                    //         'route' => [
                                    //             'name'       => 'grp.org.models.shop.prospects.upload',
                                    //             'parameters' => $this->parent->id

                                    //         ],
                                    //     ],
                                    //     [
                                    //         'type'  => 'button',
                                    //         'style' => 'create',
                                    //         'label' => __('prospect'),
                                    //         'route' => [
                                    //             'name'       => 'grp.org.shops.show.prospects.create',
                                    //             'parameters' => $request->route()->originalParameters()
                                    //         ]
                                    //     ]
                                    // ],
                                    default => []
                                }


                        ] : false
                    ],
                    // 'subNavigation' => $subNavigation,
                ]),
                'uploads'      => [
                    'templates' => [
                        'routes' => [
                            'name' => 'org.downloads.templates.prospects'
                        ]
                    ],
                    'event'     => class_basename(Contacter::class),
                    'channel'   => 'uploads.org.'.request()->user()->id
                ],
                'upload_spreadsheet' => $spreadsheetRoute ?? null,
                'uploadRoutes' => [
                    'upload'  => [
                        'name'       => 'org.models.shop.prospects.upload',
                        'parameters' => $this->parent->id
                    ],
                    'history' => [
                        'name'       => 'org.crm.prospects.uploads.history',
                        'parameters' => []
                    ],
                ],
                // ...$tabs

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Contacters'),
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

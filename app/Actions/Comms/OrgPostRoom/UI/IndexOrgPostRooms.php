<?php

/*
 * author Arya Permana - Kirin
 * created on 02-12-2024-08h-58m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\OrgPostRoom\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Comms\Traits\WithCommsSubNavigation;
use App\Actions\Comms\UI\ShowCommsDashboard;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Http\Resources\Mail\OrgPostRoomsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\OrgPostRoom;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgPostRooms extends OrgAction
{
    use WithCommsSubNavigation;

    /**
     * @var \App\Models\Fulfilment\Fulfilment
     */
    private Fulfilment|Shop $parent;

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_post_rooms.name', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrgPostRoom::class);
        $queryBuilder->where('org_post_rooms.organisation_id', $organisation->id);

        $queryBuilder
            ->defaultSort('org_post_rooms.name')
            ->select([
            'org_post_rooms.id',
            'org_post_rooms.slug',
            'org_post_rooms.type',
            'org_post_rooms.name',
            'org_post_room_stats.number_outboxes',
            'org_post_room_stats.number_mailshots',
            'org_post_room_intervals.dispatched_emails_lw',
            'org_post_room_intervals.opened_emails_lw',
            'org_post_room_intervals.unsubscribed_lw'
            ])
            ->selectRaw('org_post_room_intervals.runs_all as runs')
            ->leftJoin('org_post_room_stats', 'org_post_room_stats.org_post_room_id', '=', 'org_post_rooms.id')
            ->leftJoin('org_post_room_intervals', 'org_post_room_intervals.org_post_room_id', '=', 'org_post_rooms.id');

        return $queryBuilder
            ->allowedSorts(['name', 'runs', 'number_outboxes', 'number_mailshots', 'dispatched_emails_lw', 'opened_emails_lw', 'unsubscribed_lw'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Organisation $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            // foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            //     $table->elementGroup(
            //         key: $key,
            //         label: $elementGroup['label'],
            //         elements: $elementGroup['elements']
            //     );
            // }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title'       => __("No post room found"),
                            'count'       => $parent->commsStats->number_org_post_rooms,
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'runs', label: __('Mailshots/Runs'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'dispatched_emails_lw', label: __('Dispatched').' '.__('1w'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened_emails_lw', label: __('Opened').' '.__('1w'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unsubscribed_lw', label: __('Unsubscribed').' '.__('1w'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $orgPostRooms): AnonymousResourceCollection
    {
        return OrgPostRoomsResource::collection($orgPostRooms);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Fulfilment) {
            return $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        }

        return $request->user()->authTo([
            'shop-admin.'.$this->shop->id,
            'marketing.'.$this->shop->id.'.view',
            'web.'.$this->shop->id.'.view',
            'orders.'.$this->shop->id.'.view',
            'crm.'.$this->shop->id.'.view',
        ]);
    }


    public function htmlResponse(LengthAwarePaginator $orgPostRooms, ActionRequest $request): Response
    {
        $subNavigation = $this->getCommsNavigation($this->parent);

        $title = __('Post Room');
        $icon  = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('Post rooms')
        ];
        $afterTitle = null;
        $iconRight = null;

        return Inertia::render(
            'Comms/OrgPostRooms',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('Post Rooms'),
                'pageHead' => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    // 'container'     => $container,
                    // 'actions'       => [
                    //     $this->canEdit && $request->route()->getName() == 'grp.org.shops.show.catalogue.collections.index' ? [
                    //         'type'    => 'button',
                    //         'style'   => 'create',
                    //         'tooltip' => __('new collection'),
                    //         'label'   => __('collection'),
                    //         'route'   => [
                    //             'name'       => 'grp.org.shops.show.catalogue.collections.create',
                    //             'parameters' => $request->route()->originalParameters()
                    //         ]
                    //     ] : false,
                    //     class_basename($this->parent) == 'Collection' ? [
                    //         'type'     => 'button',
                    //         'style'    => 'secondary',
                    //         'key'      => 'attach-collection',
                    //         'icon'     => 'fal fa-plus',
                    //         'tooltip'  => __('Attach collection to this collection'),
                    //         'label'    => __('Attach collection'),
                    //     ] : false
                    // ],
                    'subNavigation' => $subNavigation,
                ],
                // 'routes'        => $routes,
                'data'          => OrgPostRoomsResource::collection($orgPostRooms),
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;

        $this->initialisationFromShop($shop, $request);
        return $this->handle($organisation);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Post Rooms'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.collections.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.dashboard.comms.post-rooms.index' =>
            array_merge(
                ShowCommsDashboard::make()->getBreadcrumbs(
                    'grp.org.shops.show.dashboard.comms.dashboard',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.dashboard.comms.post-rooms.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.fulfilments.show.operations.comms.post-rooms.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.comms.post-rooms.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Post Rooms')
                        ]
                    ]
                ]
            ),

            default => []
        };
    }
}

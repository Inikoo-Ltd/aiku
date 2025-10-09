<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\PostRoom\UI;

use App\Actions\GrpAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Mail\PostRoomResource;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPostRooms extends GrpAction
{
    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('post_rooms.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(PostRoom::class);
        $queryBuilder->where('post_rooms.group_id', $this->group->id);

        $queryBuilder
            ->defaultSort('post_rooms.name')
            ->select([
                'post_rooms.id',
                'post_rooms.slug',
                'post_rooms.name',
                'post_room_stats.number_outboxes',
                'post_room_stats.number_mailshots',
                'post_room_intervals.dispatched_emails_lw',
                'post_room_intervals.opened_emails_lw',
                'post_room_intervals.unsubscribed_lw'
            ])
            ->selectRaw('(post_room_intervals.runs_all) as runs')
            ->leftJoin('post_room_stats', 'post_room_stats.post_room_id', '=', 'post_rooms.id')
            ->leftJoin('post_room_intervals', 'post_room_intervals.post_room_id', '=', 'post_rooms.id');

        return $queryBuilder
            ->allowedSorts(['name', 'runs', 'number_outboxes', 'number_mailshots', 'dispatched_emails_lw', 'opened_emails_lw', 'unsubscribed_lw'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("group-overview");
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request);

        return $this->handle();
    }

    public function tableStructure(
        Group $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }


            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Group' => [
                            'title' => __("No post rooms found"),
                            'count' => $parent->commsStats->number_org_post_rooms,
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

    public function jsonResponse(): AnonymousResourceCollection
    {
        return PostRoomResource::collection($this->handle());
    }


    public function htmlResponse(LengthAwarePaginator $postRoom, ActionRequest $request): Response
    {
        $title      = __('Post Room');
        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('Post rooms')
        ];
        $afterTitle = null;
        $iconRight  = null;

        return Inertia::render(
            'Comms/PostRooms',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('post room'),
                'pageHead'    => [
                    'title'      => $title,
                    'icon'       => $icon,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                ],

                'data' => PostRoomResource::collection($postRoom),

            ]
        )->table($this->tableStructure($this->group));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
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
            'grp.overview.comms-marketing.post-rooms.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}

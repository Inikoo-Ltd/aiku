<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Http\Resources\Helpers\TicketResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTickets extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    protected function getElementGroups(Group $group): array
    {
        $base = Ticket::where('group_id', $group->id);

        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => collect(TicketStatusEnum::cases())->mapWithKeys(fn (TicketStatusEnum $status) => [
                    $status->value => [TicketStatusEnum::labels()[$status->value], (clone $base)->where('status', $status)->count()],
                ])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('tickets.status', $elements);
                },
            ],
            'type'   => [
                'label'    => __('Type'),
                'elements' => collect(TicketTypeEnum::cases())->mapWithKeys(fn (TicketTypeEnum $type) => [
                    $type->value => [TicketTypeEnum::labels()[$type->value], (clone $base)->where('type', $type)->count()],
                ])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('tickets.type', $elements);
                },
            ],
        ];
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('tickets.reference ILIKE ?', ["%$value%"])
                    ->orWhereRaw('tickets.subject ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Ticket::class)
            ->where('tickets.group_id', $group->id)
            ->leftJoin('users', 'users.id', '=', 'tickets.assignee_id')
            ->with(['reporter', 'customer']);

        foreach ($this->getElementGroups($group) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('-tickets.updated_at')
            ->select(['tickets.*', 'users.username as assignee_username'])
            ->allowedSorts(['reference', 'subject', 'status', 'priority', 'created_at', 'updated_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('ticket'), __('tickets')])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'reporter', label: __('Reporter'), canBeHidden: false)
                ->column(key: 'assignee', label: __('Assignee'), canBeHidden: false)
                ->column(key: 'updated_at', label: __('Updated'), canBeHidden: false, sortable: true, type: 'date')
                ->defaultSort('-updated_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $tickets): AnonymousResourceCollection
    {
        return TicketResource::collection($tickets);
    }

    public function htmlResponse(LengthAwarePaginator $tickets): Response
    {
        return Inertia::render(
            'Tickets/Tickets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Tickets'),
                'pageHead'    => [
                    'title'   => __('Tickets'),
                    'icon'    => ['fal', 'fa-life-ring'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New ticket'),
                            'route' => ['name' => 'grp.tickets.create'],
                        ],
                    ],
                ],
                'data'        => TicketResource::collection($tickets),
            ]
        )->table($this->tableStructure($this->group));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.tickets.index'],
                        'label' => __('Tickets'),
                    ],
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }
}

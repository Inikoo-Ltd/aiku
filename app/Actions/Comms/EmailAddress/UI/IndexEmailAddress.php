<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Comms\EmailAddress\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Mail\EmailAddressResource;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\EmailAddress;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmailAddress extends OrgAction
{
    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('email_addresses.email', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(EmailAddress::class);
        $queryBuilder->where('email_addresses.group_id', $this->group->id);

        $queryBuilder
            ->defaultSort('email_addresses.email')
            ->select([
                'email_addresses.id',
                'email_addresses.email',
                'email_addresses.number_marketing_dispatches',
                'email_addresses.number_transactional_dispatches',
                'email_addresses.last_marketing_dispatch_at',
                'email_addresses.last_transactional_dispatch_at',
                'email_addresses.soft_bounced_at',
                'email_addresses.hard_bounced_at',
            ]);

        return $queryBuilder
            ->allowedSorts(['email', 'marketing', 'transactional', 'last_marketing_dispatch', 'last_transactional_dispatch', 'soft_bounced', 'hard_bounced'])
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
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle();
    }

    public function tableStructure(
        Group $parent,
        ?array $modelOperations = null,
        $prefix = null
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }


            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Group' => [
                            'title' => __("No email address found"),
                            'count' => $parent->commsStats->number_email_addresses,
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'email', label: __('Email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_marketing_dispatches', label: __('Marketing'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_transactional_dispatches', label: __('Transactional'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'last_marketing_dispatch_at', label: __('Last Marketing Dispatch'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'last_transactional_dispatch_at', label: __('Last Transactional Dispatch'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'soft_bounced_at', label: __('Soft Bounced'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'hard_bounced_at', label: __('Hard Bounced'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $emailAddress, ActionRequest $request): Response
    {
        $title      = __('Email Addresses');
        $icon       = [
            'icon'  => ['fal', 'fa-envelope'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;

        return Inertia::render(
            'Comms/EmailAddresses',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'      => $title,
                    'icon'       => $icon,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                ],

                'data' => EmailAddressResource::collection($emailAddress),

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
                        'label' => __('Email Addresses'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return array_merge(
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}

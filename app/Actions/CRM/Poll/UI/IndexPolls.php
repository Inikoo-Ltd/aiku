<?php

/*
 * author Arya Permana - Kirin
 * created on 23-12-2024-15h-05m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\Poll\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\CRM\PollsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\Poll;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPolls extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;

    private Shop|Organisation $parent;


    public function handle(Shop|Organisation $parent, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($parent) {
            $query->where(function ($query) use ($value, $parent) {
                $query->whereAnyWordStartWith('polls.name', $value)
                    ->orWhereAnyWordStartWith('polls.label', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Poll::class);
        if ($parent instanceof Shop) {
            $queryBuilder->where('polls.shop_id', $parent->id);
        } else {
            $queryBuilder->where('polls.organisation_id', $parent->id);
        }

        $queryBuilder->leftJoin('poll_replies', function ($join) {
            $join->on('polls.id', '=', 'poll_replies.poll_id');
        });

        $totalCustomer = Customer::when($parent instanceof Shop, function ($query) use ($parent) {
            return $query->where('shop_id', $parent->id);
        })
        ->count();

        $queryBuilder
            ->defaultSort('polls.id')
            ->select([
                'polls.id',
                'polls.slug',
                'polls.name',
                'polls.label',
                'polls.position',
                'polls.type',
                'polls.in_registration',
                DB::raw('COUNT(DISTINCT poll_replies.customer_id) as total_replies'),
                DB::raw("'{$totalCustomer}' AS total_customers"),
            ])
            ->groupBy('polls.id');

        return $queryBuilder
            ->allowedSorts(['name', 'type', 'total_replies', 'in_registration'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Shop|Organisation $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation', 'Shop' => [
                            'title'       => __("No polls found"),
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'label', label: __('Label'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'in_registration', label: __('In registration'), canBeHidden: false, sortable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'total_replies', label: __('Customers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'percentage', label: __('Response %'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $polls): AnonymousResourceCollection
    {
        return PollsResource::collection($polls);
    }

    public function htmlResponse(LengthAwarePaginator $polls, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigation($request);
        }
        $title = __('Polls');
        $model = __('Poll');
        $icon  = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('polls')
        ];
        $afterTitle = null;
        $iconRight = null;

        if ($this->parent instanceof Shop) {
            $title = $this->parent->name;
            $model = __('poll');
            $icon  = [
                'icon'  => ['fal', 'fa-cube'],
                'title' => __('poll')
            ];
            $iconRight    = [
                'icon' => 'fal fa-cube',
            ];
            $afterTitle = [
                'label'     => __('Polls')
            ];
        }

        $action = [];
        if ($this->canEdit) {
            $action = [
                [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('New Poll'),
                    'label'   => __('New Poll'),
                    'route'   => [
                        'name'       => 'grp.org.shops.show.crm.polls.create',
                        'parameters' => $request->route()->originalParameters()
                    ]
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/Polls',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('Polls'),
                'pageHead' => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $action,
                ],
                'data'          => PollsResource::collection($polls),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);
        return $this->handle(parent: $organisation);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle(parent: $shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Polls'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.polls.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.polls.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}

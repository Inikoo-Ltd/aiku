<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Http\Resources\Procurement\OrgPartnersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgPartners extends OrgAction
{
    private Organisation $parent;

    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('organisations.code', $value)
                    ->orWhereAnyWordStartWith('organisations.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrgPartner::class);

        $queryBuilder->where('org_partners.organisation_id', $parent->id);

        $queryBuilder->leftjoin('organisations', 'org_partners.partner_id', '=', 'organisations.id');

        return $queryBuilder
            ->defaultSort('organisations.code')
            ->select([
                'org_partners.id',
                'organisations.code',
                'organisations.slug',
                'organisations.name',
                'organisations.email'
            ])
            ->allowedSorts(['code', 'organisations.name', 'email', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $parent, array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $canEdit) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('no partners found'),
                    ]
                )
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('email'), canBeHidden: false, sortable: true, searchable: true)

                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function jsonResponse(LengthAwarePaginator $partners): AnonymousResourceCollection
    {
        return OrgPartnersResource::collection($partners);
    }


    public function htmlResponse(LengthAwarePaginator $partners, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/Partners',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('partners'),
                'pageHead'    => [
                    'model'       => __('procurement'),
                    'icon'        => ['fal', 'fa-users-class'],
                    'title'       => __('partners'),
                ],
                'data'        => OrgPartnersResource::collection($partners),


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.procurement.org_partners.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.org_partners.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Partners'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            ),
        };
    }
}

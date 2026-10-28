<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * How suppliers use their page: for every supplier that can open it, how many emails may log in, how often and how
 * lately it was opened, and from where (IP, city and country).
 */
class IndexSupplierPortalActivity extends OrgAction
{
    use WithSupplyChainAuthorisation;

    private const string VISITS = '(select coalesce(sum(l.number_views), 0) from supplier_share_links l where l.supplier_id = suppliers.id)';
    private const string LAST_VISIT = '(select max(l.last_viewed_at) from supplier_share_links l where l.supplier_id = suppliers.id)';

    private function withAccess(Builder $query, Group $group): Builder
    {
        return $query->where('suppliers.group_id', $group->id)
            ->where(fn ($query) => $query
                ->whereExists(fn ($exists) => $exists->from('supplier_portal_contacts as c')->whereColumn('c.supplier_id', 'suppliers.id')->whereNull('c.revoked_at'))
                ->orWhereExists(fn ($exists) => $exists->from('supplier_share_links as l')->whereColumn('l.supplier_id', 'suppliers.id')));
    }

    private function onlyState(Builder|QueryBuilder $query, string $state): Builder|QueryBuilder
    {
        return match ($state) {
            'recent' => $query->whereRaw(self::LAST_VISIT." >= now() - interval '30 days'"),
            'never'  => $query->whereRaw(self::VISITS.' = 0'),
            default  => $query,
        };
    }

    private function selectedState(?string $prefix): ?string
    {
        $state = request()->input(($prefix ? $prefix.'_' : '').'facets.state');

        return in_array($state, ['recent', 'never'], true) ? $state : null;
    }

    /**
     * @return array{facets: array{state: list<array{value: string, label: string, count: int}>}, active_facets: array{state: string|null}}
     */
    public function facets(Group $group, ?string $prefix = null): array
    {
        $count = fn (string $state) => $this->onlyState($this->withAccess(Supplier::query(), $group), $state)->count();

        return [
            'facets'        => ['state' => [
                ['value' => 'recent', 'label' => __('Visited, last 30 days'), 'count' => $count('recent')],
                ['value' => 'never', 'label' => __('Never visited'), 'count' => $count('never')],
            ]],
            'active_facets' => ['state' => $this->selectedState($prefix)],
        ];
    }

    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('suppliers.code', $value)->orWhereAnyWordStartWith('suppliers.name', $value));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for($this->withAccess(Supplier::query(), $group));

        if ($state = $this->selectedState($prefix)) {
            $this->onlyState($query, $state);
        }

        $lastView = '(select v.%s from supplier_share_link_views v join supplier_share_links l on l.id = v.supplier_share_link_id where l.supplier_id = suppliers.id order by v.created_at desc limit 1)';

        $lastVisitSort = AllowedSort::callback('last_visit_at', fn ($query, bool $descending) => $query->orderByRaw('last_visit_at '.($descending ? 'desc' : 'asc').' nulls last'));

        return $query
            ->defaultSort($lastVisitSort->defaultDirection('desc'))
            ->select(['suppliers.id', 'suppliers.code', 'suppliers.name', 'suppliers.slug'])
            ->selectRaw('(select count(*) from supplier_portal_contacts c where c.supplier_id = suppliers.id and c.revoked_at is null) as number_emails')
            ->selectRaw('(select count(*) from supplier_portal_profiles p where p.supplier_id = suppliers.id) as number_people')
            ->selectRaw(self::VISITS.' as number_visits')
            ->selectRaw(self::LAST_VISIT.' as last_visit_at')
            ->selectRaw(sprintf($lastView, 'ip').' as last_ip')
            ->selectRaw(sprintf($lastView, 'country_code').' as last_country_code')
            ->selectRaw('(select g.city from ip_geolocations g where g.id = '.sprintf($lastView, 'ip_geolocation_id').') as last_city')
            ->allowedSorts([
                'code',
                'name',
                'number_emails',
                'number_people',
                'number_visits',
                $lastVisitSort,
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('supplier'), __('suppliers')])
                ->withEmptyState(['title' => __('No supplier can open its page yet')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_emails', label: __('Emails'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_people', label: __('People'), sortable: true, align: 'right')
                ->column(key: 'number_visits', label: __('Visits'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_visit_at', label: __('Last visit'), canBeHidden: false, sortable: true)
                ->column(key: 'last_from', label: __('From'), canBeHidden: false)
                ->defaultSort('-last_visit_at');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return JsonResource::collection($suppliers);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers): Response
    {
        return Inertia::render('SupplyChain/SupplierPortalActivity', [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'title'       => __('Supplier portal activity'),
            'pageHead'    => [
                'title' => __('Supplier portal activity'),
                'icon'  => ['icon' => ['fal', 'fa-sign-in-alt'], 'title' => __('Supplier portal activity')],
            ],
            'data'        => JsonResource::collection($suppliers)->additional($this->facets($this->group)),
        ])->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            [[
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Supplier portal activity'),
                    'icon'  => 'fal fa-bars',
                    'route' => ['name' => 'grp.supply-chain.supplier_portal_activity.index'],
                ],
            ]]
        );
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The links staff made by hand for a supplier, for suppliers that do not sign in by email.
 */
class IndexSupplierDirectLinks extends OrgAction
{
    /**
     * The Active / Inactive capsules over the table, with how many of each.
     *
     * @return array{facets: array{state: list<array{value: string, label: string, count: int}>}, active_facets: array{state: string|null}}
     */
    public function facets(Supplier $supplier, string $prefix): array
    {
        $rows = fn () => SupplierShareLink::where('supplier_share_links.supplier_id', $supplier->id)->whereNull('supplier_share_links.supplier_portal_contact_id');

        return [
            'facets'        => ['state' => [
                ['value' => 'active', 'label' => __('Active'), 'count' => $this->onlyState($rows(), 'active')->count()],
                ['value' => 'inactive', 'label' => __('Inactive'), 'count' => $this->onlyState($rows(), 'inactive')->count()],
            ]],
            'active_facets' => ['state' => $this->selectedState($prefix)],
        ];
    }

    private function selectedState(?string $prefix): ?string
    {
        $state = request()->input(($prefix ? $prefix.'_' : '').'facets.state');

        return in_array($state, ['active', 'inactive'], true) ? $state : null;
    }

    private function onlyState(Builder|QueryBuilder $query, string $state): Builder|QueryBuilder
    {
        return $state === 'active'
            ? $query->whereNull('supplier_share_links.revoked_at')->where(fn ($query) => $query->whereNull('supplier_share_links.expires_at')->orWhere('supplier_share_links.expires_at', '>', now()))
            : $query->where(fn ($query) => $query->whereNotNull('supplier_share_links.revoked_at')->orWhere('supplier_share_links.expires_at', '<=', now()));
    }

    public function handle(Supplier $supplier, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereStartWith('supplier_share_links.token_prefix', $value);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(SupplierShareLink::class)
            ->join('suppliers', 'suppliers.id', 'supplier_share_links.supplier_id')
            ->leftJoin('users', 'users.id', 'supplier_share_links.created_by_user_id')
            ->where('supplier_share_links.supplier_id', $supplier->id)
            ->whereNull('supplier_share_links.supplier_portal_contact_id');

        if ($state = $this->selectedState($prefix)) {
            $this->onlyState($query, $state);
        }

        return $query
            ->defaultSort('-created_at')
            ->select([
                'supplier_share_links.id',
                'supplier_share_links.token_prefix',
                'supplier_share_links.created_at',
                'supplier_share_links.expires_at',
                'supplier_share_links.revoked_at',
                'supplier_share_links.email',
                'supplier_share_links.number_views',
                'supplier_share_links.last_viewed_at',
                'users.contact_name as created_by',
                'suppliers.slug as supplier_slug',
            ])
            ->selectRaw('(select supplier_portal_profiles.name from supplier_portal_profiles where supplier_portal_profiles.supplier_share_link_id = supplier_share_links.id and supplier_portal_profiles.name is not null order by supplier_portal_profiles.id desc limit 1) as person_name')
            ->selectRaw('(select supplier_share_link_views.ip from supplier_share_link_views where supplier_share_link_views.supplier_share_link_id = supplier_share_links.id order by supplier_share_link_views.created_at desc limit 1) as last_visit_ip')
            ->allowedSorts(['created_at', 'expires_at', 'number_views', 'last_viewed_at'])
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
                ->withLabelRecord([__('link'), __('links')])
                ->withEmptyState(['title' => __('No direct links given yet')])
                ->column(key: 'reference', label: __('Link'), canBeHidden: false, searchable: true)
                ->column(key: 'created_at', label: __('Created'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('Status'), canBeHidden: false)
                ->column(key: 'expires_at', label: __('Works until'), canBeHidden: false, sortable: true)
                ->column(key: 'number_views', label: __('Visits'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_viewed_at', label: __('Last visit'), canBeHidden: false, sortable: true)
                ->column(key: 'last_visit_ip', label: __('From (IP)'), canBeHidden: false)
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('-created_at');
        };
    }
}

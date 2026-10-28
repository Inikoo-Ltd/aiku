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
use App\Models\SupplyChain\SupplierPortalContact;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The supplier emails allowed to ask for a link by themselves on the supplier log in page.
 */
class IndexSupplierPortalContacts extends OrgAction
{
    /**
     * The Active / Stopped capsules over the table, with how many of each.
     *
     * @return array{facets: array{state: list<array{value: string, label: string, count: int}>}, active_facets: array{state: string|null}}
     */
    public function facets(Supplier $supplier, string $prefix): array
    {
        $rows = fn () => SupplierPortalContact::where('supplier_portal_contacts.supplier_id', $supplier->id);

        return [
            'facets'        => ['state' => [
                ['value' => 'active', 'label' => __('Active'), 'count' => $this->onlyState($rows(), 'active')->count()],
                ['value' => 'inactive', 'label' => __('Stopped'), 'count' => $this->onlyState($rows(), 'inactive')->count()],
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
            ? $query->whereNull('supplier_portal_contacts.revoked_at')
            : $query->whereNotNull('supplier_portal_contacts.revoked_at');
    }

    public function handle(Supplier $supplier, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('supplier_portal_contacts.email', $value)->orWhereAnyWordStartWith('supplier_portal_profiles.name', $value)->orWhereAnyWordStartWith('supplier_portal_contacts.name', $value));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(SupplierPortalContact::class)
            ->leftJoin('supplier_portal_profiles', 'supplier_portal_profiles.supplier_portal_contact_id', 'supplier_portal_contacts.id')
            ->where('supplier_portal_contacts.supplier_id', $supplier->id);

        if ($state = $this->selectedState($prefix)) {
            $this->onlyState($query, $state);
        }

        return $query
            ->defaultSort('email')
            ->select([
                'supplier_portal_contacts.id',
                'supplier_portal_contacts.email',
                'supplier_portal_contacts.phone',
                'supplier_portal_contacts.revoked_at',
                'supplier_portal_contacts.last_link_sent_at',
                'supplier_portal_contacts.number_links_sent',
            ])
            ->selectRaw('coalesce(supplier_portal_profiles.name, supplier_portal_contacts.name) as name')
            ->selectRaw('(select coalesce(sum(supplier_share_links.number_views), 0) from supplier_share_links where supplier_share_links.supplier_portal_contact_id = supplier_portal_contacts.id) as number_visits')
            ->selectRaw('(select max(supplier_share_links.last_viewed_at) from supplier_share_links where supplier_share_links.supplier_portal_contact_id = supplier_portal_contacts.id) as last_visit_at')
            ->selectRaw('(select supplier_share_link_views.ip from supplier_share_link_views join supplier_share_links on supplier_share_links.id = supplier_share_link_views.supplier_share_link_id where supplier_share_links.supplier_portal_contact_id = supplier_portal_contacts.id order by supplier_share_link_views.created_at desc limit 1) as last_visit_ip')
            ->allowedSorts(['email', 'name', 'last_link_sent_at', 'number_links_sent', 'number_visits', 'last_visit_at'])
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
                ->withLabelRecord([__('email'), __('emails')])
                ->withEmptyState(['title' => __('No supplier email allowed yet')])
                ->column(key: 'email', label: __('Email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('Status'), canBeHidden: false)
                ->column(key: 'number_links_sent', label: __('Log ins'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_link_sent_at', label: __('Last log in'), canBeHidden: false, sortable: true)
                ->column(key: 'number_visits', label: __('Visits'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_visit_at', label: __('Last visit'), canBeHidden: false, sortable: true)
                ->column(key: 'last_visit_ip', label: __('From (IP)'), canBeHidden: false)
                ->defaultSort('email');
        };
    }
}

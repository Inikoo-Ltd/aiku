<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Models\SupplyChain\SupplierShareLink;
use App\Models\SupplyChain\SupplierShareLinkView;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every visit to the supplier portal through one direct link, or through any link an email asked for: when, from
 * where and on what.
 */
class IndexSupplierPortalVisits
{
    use AsObject;

    public function handle(SupplierShareLink|SupplierPortalContact $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('supplier_share_link_views.ip', $value)->orWhereStartWith('ip_geolocations.city', $value));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(SupplierShareLinkView::class)
            ->join('supplier_share_links', 'supplier_share_links.id', 'supplier_share_link_views.supplier_share_link_id')
            ->leftJoin('ip_geolocations', 'ip_geolocations.id', 'supplier_share_link_views.ip_geolocation_id');

        $parent instanceof SupplierShareLink
            ? $query->where('supplier_share_link_views.supplier_share_link_id', $parent->id)
            : $query->where('supplier_share_links.supplier_portal_contact_id', $parent->id);

        return $query
            ->defaultSort('-created_at')
            ->select([
                'supplier_share_link_views.id',
                'supplier_share_link_views.created_at',
                'supplier_share_link_views.ip',
                'supplier_share_link_views.user_agent',
                'supplier_share_link_views.country_code',
                'supplier_share_links.id as link_id',
                'supplier_share_links.token_prefix',
                'ip_geolocations.city',
            ])
            ->allowedSorts(['created_at', 'ip'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(SupplierShareLink|SupplierPortalContact $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('visit'), __('visits')])
                ->withEmptyState(['title' => __('No visits yet')])
                ->column(key: 'created_at', label: __('When'), canBeHidden: false, sortable: true)
                ->column(key: 'from', label: __('From'), canBeHidden: false)
                ->column(key: 'ip', label: __('IP'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'device', label: __('Device'), canBeHidden: false);

            if ($parent instanceof SupplierPortalContact) {
                $table->column(key: 'reference', label: __('Link'), canBeHidden: false);
            }

            $table->defaultSort('-created_at');
        };
    }
}

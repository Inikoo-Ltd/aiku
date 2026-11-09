<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 17:03:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\Competitor;
use App\Models\Masters\CompetitorPriceCheck;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every price a person checked on a marketplace, newest first, with the link to the listing and the
 * change since the check before for the same listing.
 */
class IndexCompetitorPriceChecks extends OrgAction
{
    use WithMastersAuthorisation;

    public function handle(Competitor $competitor, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_assets.code', $value)
                    ->orWhereAnyWordStartWith('master_assets.name', $value)
                    ->orWhereAnyWordStartWith('competitor_products.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(CompetitorPriceCheck::class)
            ->join('master_assets', 'master_assets.id', '=', 'competitor_price_checks.master_asset_id')
            ->join('competitor_products', 'competitor_products.id', '=', 'competitor_price_checks.competitor_product_id')
            ->join('currencies', 'currencies.id', '=', 'competitor_price_checks.currency_id')
            ->leftJoin('users', 'users.id', '=', 'competitor_price_checks.checked_by_user_id')
            ->where('competitor_price_checks.competitor_id', $competitor->id)
            ->select([
                'competitor_price_checks.id',
                'competitor_price_checks.checked_at',
                'competitor_price_checks.price',
                'competitor_price_checks.units',
                'competitor_price_checks.delivery_days',
                'competitor_price_checks.image_id',
                'competitor_price_checks.our_unit_price',
                'competitor_price_checks.their_unit_price',
                'competitor_price_checks.difference_pct',
                'master_assets.code as master_asset_code',
                'master_assets.name as master_asset_name',
                'master_assets.slug as master_asset_slug',
                'competitor_products.name as competitor_product_name',
                'competitor_products.url',
                'currencies.code as unit_currency_code',
                'users.contact_name as checked_by',
            ])
            ->selectRaw('competitor_price_checks.their_unit_price - lag(competitor_price_checks.their_unit_price) over (partition by competitor_price_checks.competitor_product_id, competitor_price_checks.currency_id order by competitor_price_checks.checked_at, competitor_price_checks.id) as change')
            ->with('image')
            ->allowedFilters([$globalSearch])
            ->defaultSort('-checked_at')
            ->allowedSorts(['checked_at', 'difference_pct', 'their_unit_price', 'delivery_days', 'master_asset_code'])
            ->orderByDesc('competitor_price_checks.id')
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
                ->withEmptyState([
                    'title'       => __('No prices checked yet'),
                    'description' => __('Open their website, find one of our products and add the price you see with Add price check'),
                ])
                ->column(key: 'checked_at', label: __('Checked'), sortable: true)
                ->column(key: 'master_asset_code', label: __('Our product'), sortable: true, searchable: true)
                ->column(key: 'screenshot', label: __('Screenshot'))
                ->column(key: 'competitor_product_name', label: __('Their listing'))
                ->column(key: 'price', label: __('Their price'), align: 'right')
                ->column(key: 'their_unit_price', label: __('Theirs per unit'), sortable: true, align: 'right')
                ->column(key: 'our_unit_price', label: __('Ours per unit'), align: 'right')
                ->column(key: 'difference_pct', label: __('Difference'), sortable: true, align: 'right')
                ->column(key: 'change', label: __('Since last check'), align: 'right')
                ->column(key: 'delivery_days', label: __('Delivery'), sortable: true, align: 'right')
                ->column(key: 'checked_by', label: __('By'))
                ->defaultSort('-checked_at');
        };
    }
}

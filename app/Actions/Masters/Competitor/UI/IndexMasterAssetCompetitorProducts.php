<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Http\Resources\Masters\MasterAssetCompetitorProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Our products next to what competitors charge, in buckets: to review (the AI suggested the match),
 * confirmed (the price comparison), rejected by staff, or all. Matches the AI itself turned down are
 * never listed.
 */
class IndexMasterAssetCompetitorProducts extends OrgAction
{
    use WithMastersAuthorisation;
    use WithCompetitorPriceComparison;
    use WithCompetitorsSubNavigation;

    private string $bucket = 'to_review';

    public function handle(Group|Competitor $parent, string $bucket = 'to_review', ?string $prefix = null, ?string $sort = null, ?int $limit = null): LengthAwarePaginator
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

        $group = $parent instanceof Group ? $parent : $parent->group;
        $sql   = $this->competitorPriceSql($group);

        $query = QueryBuilder::for(MasterAssetCompetitorProduct::class)
            ->join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
            ->join('master_shops', 'master_shops.id', '=', 'master_asset_competitor_products.master_shop_id')
            ->join('competitor_products', 'competitor_products.id', '=', 'master_asset_competitor_products.competitor_product_id')
            ->join('competitors', 'competitors.id', '=', 'competitor_products.competitor_id')
            ->when($parent instanceof Group, fn ($query) => $query->where('master_assets.group_id', $parent->id))
            ->when($parent instanceof Competitor, fn ($query) => $query->where('competitors.id', $parent->id));

        match ($bucket) {
            'confirmed' => $query->where('master_asset_competitor_products.status', MasterAssetCompetitorProductStatusEnum::CONFIRMED),
            'rejected'  => $query->where('master_asset_competitor_products.status', MasterAssetCompetitorProductStatusEnum::REJECTED)->whereNotNull('master_asset_competitor_products.reviewed_by_user_id'),
            'all'       => $query->where(fn ($query) => $query->where('master_asset_competitor_products.status', '!=', MasterAssetCompetitorProductStatusEnum::REJECTED)
                ->orWhereNotNull('master_asset_competitor_products.reviewed_by_user_id')),
            default     => $query->where('master_asset_competitor_products.status', MasterAssetCompetitorProductStatusEnum::SUGGESTED),
        };

        if ($limit) {
            $query->where('master_asset_competitor_products.is_same_item', true)
                ->whereRaw($sort === 'difference' ? "{$sql['difference']} < 0" : "{$sql['difference']} > 0");
        }

        return $query
            ->select([
                'master_asset_competitor_products.id',
                'master_asset_competitor_products.status',
                'master_asset_competitor_products.is_same_item',
                'master_asset_competitor_products.confidence',
                'master_asset_competitor_products.reviewed_by_user_id',
                'competitor_products.image_url as competitor_image_url',
                'master_assets.code',
                'master_assets.slug',
                'master_assets.name',
                'master_shops.slug as master_shop_slug',
                'master_shops.name as master_shop_name',
                'competitors.id as competitor_id',
                'competitors.name as competitor_name',
                'competitor_products.name as competitor_product_name',
                'competitor_products.url as competitor_product_url',
                'competitor_products.units as competitor_units',
                'competitor_products.minimum_order',
                'competitor_products.fetched_at',
            ])
            ->selectRaw("{$sql['ours']} as our_unit_price, {$sql['theirs']} as competitor_unit_price, {$sql['difference']} as difference, ? as currency_code", [$group->currency->code])
            ->allowedFilters([$globalSearch])
            ->defaultSort($sort ?? '-is_same_item')
            ->allowedSorts(['code', 'competitor_name', 'status', 'is_same_item', 'difference', 'master_shop_name'])
            ->withPaginator($prefix, $limit, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null, bool $withMasterShop = true, ?string $sort = null, bool $withReview = true): Closure
    {
        return function (InertiaTable $table) use ($prefix, $withMasterShop, $sort, $withReview) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No competitor prices here'),
                    'description' => __('Competitor products are matched to ours after each read; the AI suggests the matches it is not sure of'),
                ])
                ->column(key: 'code', label: __('Product'), sortable: true, searchable: true);
            if ($withMasterShop) {
                $table->column(key: 'master_shop_name', label: __('Master shop'), sortable: true);
            }
            $table
                ->column(key: 'our_unit_price', label: __('Ours / unit'), align: 'right')
                ->column(key: 'competitor_name', label: __('Competitor'), sortable: true)
                ->column(key: 'competitor_product_name', label: __('Their product'))
                ->column(key: 'competitor_unit_price', label: __('Theirs / unit'), align: 'right')
                ->column(key: 'difference', label: __('Difference'), align: 'right', sortable: true);
            if ($withReview) {
                $table->column(key: 'status', label: __('Right match?'), sortable: true);
            }
            $table->defaultSort($sort ?? '-is_same_item');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), $this->bucket);
    }

    public function confirmed(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'confirmed';

        return $this->asController($request);
    }

    public function rejected(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'rejected';

        return $this->asController($request);
    }

    public function allMatches(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';

        return $this->asController($request);
    }

    public function htmlResponse(LengthAwarePaginator $matches, ActionRequest $request): Response
    {
        $title = match ($this->bucket) {
            'confirmed' => __('Confirmed competitor prices'),
            'rejected'  => __('Rejected matches'),
            'all'       => __('All competitor matches'),
            default     => __('Prices to review'),
        };

        return Inertia::render(
            'Masters/CompetitorPrices',
            [
                'breadcrumbs' => $this->getCompetitorsBreadcrumbs($request->route()->getName(), $title),
                'title'       => $title,
                'pageHead'    => [
                    'title'          => $title,
                    'icon'           => ['icon' => ['fal', 'fa-balance-scale'], 'title' => $title],
                    'subNavigation'  => $this->getCompetitorsSubNavigation($this->group),
                    'subNavigation2' => $this->getBucketsSubNavigation($this->group),
                ],
                'data'        => MasterAssetCompetitorProductsResource::collection($matches),
                'can_edit'    => $this->canEdit,
            ]
        )->table($this->tableStructure());
    }

    public function getBucketsSubNavigation(Group $group): array
    {
        $counts = MasterAssetCompetitorProduct::join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
            ->where('master_assets.group_id', $group->id)
            ->selectRaw("count(*) filter (where master_asset_competitor_products.status = 'suggested') as to_review, count(*) filter (where master_asset_competitor_products.status = 'confirmed') as confirmed, count(*) filter (where master_asset_competitor_products.status = 'rejected' and reviewed_by_user_id is not null) as rejected")
            ->first();

        $bucket = fn (string $label, string $route, int $number, ?string $icon = null, ?string $align = null) => array_filter([
            'label'  => $label,
            'icon'   => $icon,
            'align'  => $align,
            'root'   => $route,
            'route'  => ['name' => $route, 'parameters' => []],
            'number' => $number,
        ], fn ($value) => $value !== null);

        return [
            $bucket(__('To review'), 'grp.masters.competitors.prices.index', (int) $counts->to_review),
            $bucket(__('Confirmed'), 'grp.masters.competitors.prices.confirmed', (int) $counts->confirmed),
            $bucket(__('Rejected'), 'grp.masters.competitors.prices.rejected', (int) $counts->rejected),
            $bucket(__('All'), 'grp.masters.competitors.prices.all', (int) $counts->to_review + (int) $counts->confirmed + (int) $counts->rejected, 'fal fa-bars', 'right'),
        ];
    }
}

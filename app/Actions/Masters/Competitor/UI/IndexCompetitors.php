<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Masters\CompetitorsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCompetitors extends OrgAction
{
    use WithMastersAuthorisation;
    use WithCompetitorPriceComparison;
    use WithCompetitorsSubNavigation;

    public function handle(Group $group, ?string $prefix = null, ?string $sort = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $sql       = $this->competitorPriceSql($group);
        $confirmed = DB::table('master_asset_competitor_products')
            ->join('competitor_products', 'competitor_products.id', '=', 'master_asset_competitor_products.competitor_product_id')
            ->join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
            ->join('competitors', 'competitors.id', '=', 'competitor_products.competitor_id')
            ->where('master_asset_competitor_products.status', 'confirmed')
            ->where('master_asset_competitor_products.is_same_item', true)
            ->groupBy('competitors.id')
            ->selectRaw("competitors.id as competitor_id, count(*) as number_confirmed, percentile_cont(0.5) within group (order by {$sql['difference']}) as median_difference");

        $query = QueryBuilder::for(Competitor::class)
            ->where('competitors.group_id', $group->id)
            ->leftJoin('currencies', 'currencies.id', '=', 'competitors.currency_id')
            ->join('master_shops', 'master_shops.id', '=', 'competitors.master_shop_id')
            ->leftJoinSub($confirmed, 'confirmed', 'confirmed.competitor_id', '=', 'competitors.id')
            ->select([
                'competitors.id',
                'competitors.name',
                'competitors.website',
                'competitors.sells_to',
                'competitors.status',
                'competitors.last_error',
                'competitors.fetched_at',
                'competitors.number_products',
                'competitors.feed_url',
                'currencies.code as currency_code',
                'master_shops.slug as master_shop_slug',
                'master_shops.name as master_shop_name',
            ])
            ->selectRaw('competitors.username is not null as has_login')
            ->selectRaw('competitors.search_url is not null as has_search')
            ->selectRaw('coalesce(confirmed.number_confirmed, 0) as number_confirmed, round(confirmed.median_difference) as median_difference');

        foreach ($this->getElementGroups($group) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $query
            ->defaultSort($sort ?? 'name')
            ->allowedSorts(['name', 'fetched_at', 'number_products', 'number_confirmed', 'median_difference', 'master_shop_name', 'status'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function getElementGroups(Group $group): array
    {
        $masterShops = MasterShop::where('group_id', $group->id)->orderBy('code')->get();
        $counts      = Competitor::where('group_id', $group->id)->selectRaw('master_shop_id, count(*) as number')->groupBy('master_shop_id')->pluck('number', 'master_shop_id');

        return [
            'master_shop' => [
                'label'    => __('Master shop'),
                'elements' => $masterShops->mapWithKeys(fn (MasterShop $masterShop) => [$masterShop->slug => [$masterShop->name, (int) ($counts[$masterShop->id] ?? 0)]])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('master_shops.slug', $elements);
                },
            ],
        ];
    }

    public function tableStructure(Group $group, ?string $prefix = null, ?string $sort = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix, $sort) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withEmptyState([
                    'title'       => __('No competitors yet'),
                    'description' => __('Add the websites of the competitors whose prices you want to know'),
                ])
                ->column(key: 'status', label: '', type: 'icon')
                ->column(key: 'name', label: __('Name'), sortable: true)
                ->column(key: 'master_shop_name', label: __('Master shop'), sortable: true)
                ->column(key: 'sells_to', label: __('Sells to'))
                ->column(key: 'read_method', label: __('Prices read from'))
                ->column(key: 'fetched_at', label: __('Last read'), sortable: true)
                ->column(key: 'number_products', label: __('Products found'), sortable: true, align: 'right')
                ->column(key: 'number_confirmed', label: __('Confirmed matches'), sortable: true, align: 'right')
                ->column(key: 'median_difference', label: __('Median difference'), sortable: true, align: 'right')
                ->defaultSort($sort ?? 'name');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function htmlResponse(LengthAwarePaginator $competitors, ActionRequest $request): Response
    {
        return Inertia::render(
            'Masters/Competitors',
            [
                'breadcrumbs' => $this->getCompetitorsBreadcrumbs('grp.masters.competitors.index', __('Competitors')),
                'title'       => __('Competitors'),
                'pageHead'    => [
                    'title'         => __('Competitors'),
                    'icon'          => ['icon' => ['fal', 'fa-binoculars'], 'title' => __('Competitors')],
                    'subNavigation' => $this->getCompetitorsSubNavigation($this->group),
                    'actions'       => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Add competitor'),
                            'route' => ['name' => 'grp.masters.competitors.create', 'parameters' => []],
                        ],
                    ] : [],
                ],
                'data'        => CompetitorsResource::collection($competitors),
                'can_edit'    => $this->canEdit,
            ]
        )->table($this->tableStructure($this->group));
    }
}

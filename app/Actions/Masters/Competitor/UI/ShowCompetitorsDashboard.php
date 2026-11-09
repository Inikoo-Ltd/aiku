<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Masters\Competitor\CompetitorStatusEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Http\Resources\Masters\CompetitorsResource;
use App\Http\Resources\Masters\MasterAssetCompetitorProductsResource;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * "About the same" is within 5% either way. A week of throughput counts the matches staff reviewed
 * plus the ones the AI confirmed by itself that week.
 */
class ShowCompetitorsDashboard extends OrgAction
{
    use WithMastersAuthorisation;
    use WithCompetitorPriceComparison;
    use WithCompetitorsSubNavigation;

    public const int SAME_PRICE_BAND = 5;

    /**
     * @param  array<int>|null  $masterShopIds  only these master shops, for an organisation or shop dashboard
     * @param  bool  $withActivity  the weekly review counts and the 30-day trend, which the one-line summary does not show
     */
    public function handle(Group $group, ?array $masterShopIds = null, bool $withActivity = true): array
    {
        $competitors = Competitor::where('group_id', $group->id)
            ->when($masterShopIds !== null, fn ($query) => $query->whereIn('master_shop_id', $masterShopIds))
            ->get();
        if ($competitors->isEmpty()) {
            return ['number_competitors' => 0];
        }

        $sql     = $this->competitorPriceSql($group);
        $matches = DB::table('master_asset_competitor_products')
            ->join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
            ->join('competitor_products', 'competitor_products.id', '=', 'master_asset_competitor_products.competitor_product_id')
            ->join('competitors', 'competitors.id', '=', 'competitor_products.competitor_id')
            ->where('master_assets.group_id', $group->id)
            ->when($masterShopIds !== null, fn ($query) => $query->whereIn('master_asset_competitor_products.master_shop_id', $masterShopIds));

        $band     = self::SAME_PRICE_BAND;
        $position = (clone $matches)
            ->where('master_asset_competitor_products.status', 'confirmed')
            ->where('master_asset_competitor_products.is_same_item', true)
            ->whereNotNull(DB::raw($sql['difference']))
            ->groupBy('master_asset_competitor_products.master_shop_id')
            ->selectRaw("master_asset_competitor_products.master_shop_id,
                count(*) filter (where {$sql['difference']} > $band) as cheaper,
                count(*) filter (where {$sql['difference']} between -$band and $band) as same,
                count(*) filter (where {$sql['difference']} < -$band) as dearer,
                round(percentile_cont(0.5) within group (order by {$sql['difference']})) as median_difference,
                count(distinct master_asset_competitor_products.master_asset_id) as products_with_price")
            ->get()->keyBy('master_shop_id');

        $activeProducts = DB::table('master_assets')
            ->where('group_id', $group->id)->where('type', MasterAssetTypeEnum::PRODUCT->value)->where('status', true)->where('is_main', true)
            ->groupBy('master_shop_id')->selectRaw('master_shop_id, count(*) as number')->pluck('number', 'master_shop_id');

        $byMasterShop = MasterShop::where('group_id', $group->id)->orderBy('code')->get()
            ->filter(fn (MasterShop $masterShop) => $competitors->contains('master_shop_id', $masterShop->id))
            ->map(fn (MasterShop $masterShop) => [
                'slug'                => $masterShop->slug,
                'name'                => $masterShop->name,
                'cheaper'             => (int) ($position[$masterShop->id]->cheaper ?? 0),
                'same'                => (int) ($position[$masterShop->id]->same ?? 0),
                'dearer'              => (int) ($position[$masterShop->id]->dearer ?? 0),
                'median_difference'   => isset($position[$masterShop->id]->median_difference) ? (int) $position[$masterShop->id]->median_difference : null,
                'products_with_price' => (int) ($position[$masterShop->id]->products_with_price ?? 0),
                'active_products'     => (int) ($activeProducts[$masterShop->id] ?? 0),
            ])->values();

        $statusLabels = [
            CompetitorStatusEnum::ERROR->value        => __('Could not open the website'),
            CompetitorStatusEnum::BLOCKED->value      => __('The website blocked us'),
            CompetitorStatusEnum::LOGIN_FAILED->value => __('Could not log in'),
            CompetitorStatusEnum::SEARCH_BROKEN->value => __('The search link does not search'),
        ];
        $failing = [CompetitorStatusEnum::ERROR, CompetitorStatusEnum::BLOCKED, CompetitorStatusEnum::LOGIN_FAILED, CompetitorStatusEnum::SEARCH_BROKEN];

        $activity = $withActivity ? $this->activity($matches) : ['throughput' => null, 'trend' => null];

        return [
            'number_competitors' => $competitors->count(),
            'status'             => [
                'ok'       => $competitors->where('status', CompetitorStatusEnum::OK)->count(),
                'failing'  => $competitors->whereIn('status', $failing)->count(),
                'not_read' => $competitors->whereNull('status')->count(),
                'last_read' => $competitors->max('fetched_at'),
                'failures' => $competitors->whereIn('status', $failing)
                    ->map(fn (Competitor $competitor) => [
                        'id'         => $competitor->id,
                        'slug'       => $competitor->slug,
                        'name'       => $competitor->name,
                        'reason'     => $statusLabels[$competitor->status->value],
                        'last_error' => $competitor->last_error,
                        'fetched_at' => $competitor->fetched_at,
                    ])->values(),
            ],
            'to_review'          => MasterAssetCompetitorProduct::join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
                ->where('master_assets.group_id', $group->id)->where('master_asset_competitor_products.status', 'suggested')
                ->when($masterShopIds !== null, fn ($query) => $query->whereIn('master_asset_competitor_products.master_shop_id', $masterShopIds))
                ->count(),
            'throughput'         => $activity['throughput'],
            'by_master_shop'     => $byMasterShop,
            'trend'              => $activity['trend'],
        ];
    }

    /**
     * @return array{throughput: array, trend: Collection}
     */
    private function activity(Builder $matches): array
    {
        $weekStart = now()->startOfWeek();
        $throughput = fn ($from, $to) => (clone $matches)
            ->selectRaw("count(*) filter (where reviewed_at >= ? and reviewed_at < ?) as reviewed,
                count(*) filter (where master_asset_competitor_products.status = 'confirmed' and coalesce(reviewed_at, master_asset_competitor_products.created_at) >= ? and coalesce(reviewed_at, master_asset_competitor_products.created_at) < ?) as confirmed", [$from, $to, $from, $to])
            ->first();
        $thisWeek = $throughput($weekStart, $weekStart->copy()->addWeek());
        $lastWeek = $throughput($weekStart->copy()->subWeek(), $weekStart);

        $trendStart = now()->startOfDay()->subDays(29);
        $trend      = (clone $matches)
            ->where('master_asset_competitor_products.status', 'confirmed')
            ->whereRaw('coalesce(reviewed_at, master_asset_competitor_products.created_at) >= ?', [$trendStart])
            ->selectRaw("coalesce(reviewed_at, master_asset_competitor_products.created_at)::date as day, count(*) as number")
            ->groupBy('day')->pluck('number', 'day');

        return [
            'throughput' => [
                'reviewed'  => ['this_week' => (int) $thisWeek->reviewed, 'last_week' => (int) $lastWeek->reviewed],
                'confirmed' => ['this_week' => (int) $thisWeek->confirmed, 'last_week' => (int) $lastWeek->confirmed],
            ],
            'trend'      => collect(range(0, 29))->map(function (int $days) use ($trendStart, $trend) {
                $day = $trendStart->copy()->addDays($days)->toDateString();

                return ['day' => $day, 'confirmed' => (int) ($trend[$day] ?? 0)];
            }),
        ];
    }

    /**
     * The one line under the month target on the group, organisation and shop dashboards.
     *
     * @param  array<int>|null  $masterShopIds
     * @return array{number_competitors: int, cheaper: int, same: int, dearer: int, to_review: int, failing: int}|null
     */
    public static function summary(Group $group, ?array $masterShopIds = null): ?array
    {
        $dashboard = static::run($group, $masterShopIds, false);
        if (!$dashboard['number_competitors']) {
            return null;
        }

        $byMasterShop = collect($dashboard['by_master_shop']);

        return [
            'number_competitors' => $dashboard['number_competitors'],
            'cheaper'            => $byMasterShop->sum('cheaper'),
            'same'               => $byMasterShop->sum('same'),
            'dearer'             => $byMasterShop->sum('dearer'),
            'to_review'          => $dashboard['to_review'],
            'failing'            => $dashboard['status']['failing'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function htmlResponse(array $dashboard, ActionRequest $request): Response
    {
        $group = $this->group;

        return Inertia::render(
            'Masters/CompetitorsDashboard',
            [
                'breadcrumbs' => $this->getCompetitorsBreadcrumbs(),
                'title'       => __('Competitors'),
                'pageHead'    => [
                    'title'         => __('Competitors'),
                    'icon'          => ['icon' => ['fal', 'fa-binoculars'], 'title' => __('Competitors')],
                    'subNavigation' => $this->getCompetitorsSubNavigation($group),
                    'actions'       => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Add competitor'),
                            'route' => ['name' => 'grp.masters.competitors.create', 'parameters' => []],
                        ],
                    ] : [],
                ],
                'dashboard'   => $dashboard,
                'can_edit'    => $this->canEdit,
                'competitors'  => fn () => CompetitorsResource::collection(IndexCompetitors::run($group, 'competitors', '-number_confirmed')),
                'they_cheaper' => fn () => MasterAssetCompetitorProductsResource::collection(IndexMasterAssetCompetitorProducts::run($group, 'confirmed', 'they_cheaper', 'difference', 10)),
                'we_cheaper'   => fn () => MasterAssetCompetitorProductsResource::collection(IndexMasterAssetCompetitorProducts::run($group, 'confirmed', 'we_cheaper', '-difference', 10)),
            ]
        )->table(IndexCompetitors::make()->tableStructure($group, 'competitors', '-number_confirmed'))
            ->table(IndexMasterAssetCompetitorProducts::make()->tableStructure('they_cheaper', sort: 'difference', withReview: false))
            ->table(IndexMasterAssetCompetitorProducts::make()->tableStructure('we_cheaper', sort: '-difference', withReview: false));
    }
}

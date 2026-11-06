<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\Masters\Competitor\FetchCompetitorIntel;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use App\Enums\UI\Masters\CompetitorTabsEnum;
use App\Http\Resources\Masters\CompetitorProductsResource;
use App\Http\Resources\Masters\CompetitorsResource;
use App\Http\Resources\Masters\MasterAssetCompetitorProductsResource;
use App\Models\Masters\Competitor;
use App\Models\Masters\CompetitorDailyStat;
use App\Models\Masters\CompetitorIntelSnapshot;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCompetitor extends OrgAction
{
    use WithMastersAuthorisation;
    use WithCompetitorsSubNavigation;

    private const string DISCOUNTED = 'discounted';

    public function handle(Competitor $competitor): Competitor
    {
        return $competitor;
    }

    public function asController(Competitor $competitor, ActionRequest $request): Competitor
    {
        $this->initialisationFromGroup(group(), $request)->withTab(CompetitorTabsEnum::values());

        return $this->handle($competitor);
    }

    public function htmlResponse(Competitor $competitor, ActionRequest $request): Response
    {
        $intel = $competitor->intel ?? [];

        return Inertia::render(
            'Masters/Competitor',
            [
                'title'       => __('Competitor').': '.$competitor->name,
                'breadcrumbs' => array_merge(
                    $this->getCompetitorsBreadcrumbs('grp.masters.competitors.index', __('Competitors')),
                    [['type' => 'simple', 'simple' => ['label' => $competitor->name]]]
                ),
                'pageHead'    => [
                    'title'         => $competitor->name,
                    'model'         => __('Competitor'),
                    'icon'          => ['icon' => ['fal', 'fa-binoculars'], 'title' => __('Competitor')],
                    'subNavigation' => $this->getCompetitorsSubNavigation($this->group),
                    'actions'       => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => ['name' => 'grp.masters.competitors.edit', 'parameters' => ['competitor' => $competitor->id]],
                        ],
                    ] : [],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => CompetitorTabsEnum::navigation(),
                ],
                'competitor'  => [
                    'name'             => $competitor->name,
                    'website'          => $competitor->website,
                    'master_shop'      => ['name' => $competitor->masterShop->name, 'slug' => $competitor->masterShop->slug],
                    'sells_to'         => CompetitorSellsToEnum::labels()[$competitor->sells_to->value],
                    'currency_code'    => $competitor->currency->code,
                    'language'         => $competitor->language?->name,
                    'read_method'      => CompetitorsResource::readMethod($competitor->feed_url, (bool) $competitor->search_url),
                    'has_login'        => (bool) $competitor->username,
                    'status'           => $competitor->status?->value,
                    'last_error'       => $competitor->last_error,
                    'fetched_at'       => $competitor->fetched_at,
                    'number_products'  => $competitor->number_products,
                    'platform'         => Arr::get($intel, 'homepage.platform'),
                    'logo'             => Arr::get($intel, 'homepage.logo'),
                    'intel_fetched_at' => $competitor->intel_fetched_at,
                    'intel_error'      => $competitor->intel_error,
                    'screenshot'       => $competitor->image_id ? $competitor->imageSources(1366, 0) : null,
                ],
                'can_edit'    => $this->canEdit,
                'refresh_route' => ['name' => 'grp.models.competitor.refresh_intel', 'parameters' => ['competitor' => $competitor->id]],

                CompetitorTabsEnum::DOSSIER->value => $this->tab == CompetitorTabsEnum::DOSSIER->value
                    ? fn () => $this->dossier($competitor)
                    : Inertia::optional(fn () => $this->dossier($competitor)),

                CompetitorTabsEnum::MATCHES->value => $this->tab == CompetitorTabsEnum::MATCHES->value
                    ? fn () => MasterAssetCompetitorProductsResource::collection(IndexMasterAssetCompetitorProducts::run($competitor, 'all', CompetitorTabsEnum::MATCHES->value))
                    : Inertia::optional(fn () => MasterAssetCompetitorProductsResource::collection(IndexMasterAssetCompetitorProducts::run($competitor, 'all', CompetitorTabsEnum::MATCHES->value))),

                CompetitorTabsEnum::PRODUCTS->value => $this->tab == CompetitorTabsEnum::PRODUCTS->value
                    ? fn () => CompetitorProductsResource::collection(IndexCompetitorProducts::run($competitor, CompetitorTabsEnum::PRODUCTS->value))
                    : Inertia::optional(fn () => CompetitorProductsResource::collection(IndexCompetitorProducts::run($competitor, CompetitorTabsEnum::PRODUCTS->value))),

                CompetitorTabsEnum::POLICIES->value => $this->tab == CompetitorTabsEnum::POLICIES->value
                    ? fn () => Arr::get($intel, 'pages', [])
                    : Inertia::optional(fn () => Arr::get($intel, 'pages', [])),
            ]
        )->table(IndexCompetitorProducts::make()->tableStructure($competitor, self::DISCOUNTED, onSaleOnly: true))
            ->table(IndexMasterAssetCompetitorProducts::make()->tableStructure(CompetitorTabsEnum::MATCHES->value, withMasterShop: false))
            ->table(IndexCompetitorProducts::make()->tableStructure($competitor, CompetitorTabsEnum::PRODUCTS->value));
    }

    /**
     * The dossier tab: the intel as read (without the page texts, which the Policies tab shows), the
     * changes since the refresh before with a short timeline, today's numbers, 90 days of offers and the
     * most discounted products. All in one prop, as a tab change reloads only the tab's own prop.
     */
    private function dossier(Competitor $competitor): array
    {
        $snapshots = $competitor->intelSnapshots()->latest('fetched_at')->latest('id')->limit(12)->get(['id', 'fetched_at', 'changes']);
        $latest    = $snapshots->first();

        return [
            'intel'      => Arr::except($competitor->intel ?? [], ['pages']),
            'numbers'    => FetchCompetitorIntel::numbers($competitor),
            'discounted' => CompetitorProductsResource::collection(IndexCompetitorProducts::run($competitor, self::DISCOUNTED, onSaleOnly: true)),
            'changes'  => $latest ? [
                'fetched_at'  => $latest->fetched_at,
                'previous_at' => $snapshots->get(1)?->fetched_at,
                'items'       => static::changeLines($latest),
            ] : null,
            'timeline' => $snapshots->slice(1)->filter(fn (CompetitorIntelSnapshot $snapshot) => $snapshot->changes)->values()
                ->map(fn (CompetitorIntelSnapshot $snapshot) => ['fetched_at' => $snapshot->fetched_at, 'items' => static::changeLines($snapshot)])
                ->all(),
            'on_sale'  => CompetitorDailyStat::where('competitor_id', $competitor->id)
                ->where('date', '>=', now()->subDays(90)->toDateString())
                ->orderBy('date')
                ->get()
                ->map(fn (CompetitorDailyStat $stat) => [
                    'date'             => $stat->date->toDateString(),
                    'share_on_sale'    => $stat->number_products ? round(100 * $stat->number_on_sale / $stat->number_products, 1) : null,
                    'average_discount' => $stat->average_discount === null ? null : (float) $stat->average_discount,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array{label: string, from: string|null, to: string|null}>
     */
    private static function changeLines(CompetitorIntelSnapshot $snapshot): array
    {
        return array_map(fn (array $change) => [
            'label' => __(FetchCompetitorIntel::CHANGE_LABELS[$change['key']] ?? $change['key']),
            'from'  => $change['from'],
            'to'    => $change['to'],
        ], $snapshot->changes);
    }
}

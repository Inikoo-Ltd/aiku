<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Seo\GetBacklinkGap;
use App\Actions\Web\Seo\StoreSerpResult;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Enums\UI\Web\SeoBacklinksTabsEnum;
use App\Http\Resources\Web\SeoBacklinkResource;
use App\Http\Resources\Web\SeoReferringDomainResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoBacklinkSummary;
use App\Models\Web\SeoCompetitor;
use App\Models\Web\SeoReferringDomain;
use App\Services\DataForSeo\DataForSeoClient;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoBacklinks extends OrgAction
{
    use WithWebAuthorisation;

    private const int HISTORY_RUNS = 26;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoBacklinksTabsEnum::values());

        return $shop;
    }

    private static function summary(?SeoBacklinkSummary $summary): ?array
    {
        return $summary ? [
            'date'                   => $summary->date->toDateString(),
            'rank'                   => $summary->rank,
            'backlinks'              => $summary->backlinks,
            'referring_domains'      => $summary->referring_domains,
            'broken_backlinks'       => $summary->broken_backlinks,
            'new_referring_domains'  => $summary->new_referring_domains,
            'lost_referring_domains' => $summary->lost_referring_domains,
        ] : null;
    }

    private function overview(Shop $shop, string $domain): array
    {
        $summaries = SeoBacklinkSummary::where('domain', $domain)->orderByDesc('date')->limit(self::HISTORY_RUNS)->get();

        $links = DB::table('seo_backlinks')
            ->where('website_id', $shop->website->id)
            ->where('is_own_website', false)
            ->selectRaw('COUNT(*) FILTER (WHERE lost_at IS NULL AND first_seen >= ?) AS new', [now()->subDays(IndexSeoBacklinks::RECENT_DAYS)])
            ->selectRaw('COUNT(*) FILTER (WHERE lost_at >= ?) AS lost', [now()->subDays(IndexSeoBacklinks::RECENT_DAYS)])
            ->selectRaw('COUNT(*) FILTER (WHERE lost_at IS NULL AND is_broken) AS broken')
            ->selectRaw('MAX(fetched_at) AS fetched_at')
            ->first();

        $competitors     = $shop->seoCompetitors()->orderBy('domain')->get();
        $competitorLatest = SeoBacklinkSummary::query()
            ->whereIn('domain', $competitors->map(fn (SeoCompetitor $competitor) => StoreSerpResult::normaliseDomain($competitor->domain)))
            ->orderByDesc('date')
            ->get()
            ->unique('domain')
            ->keyBy('domain');

        return [
            'latest'      => self::summary($summaries->first()),
            'previous'    => self::summary($summaries->skip(1)->first()),
            'history'     => $summaries->reverse()->map(fn (SeoBacklinkSummary $summary) => self::summary($summary))->values()->all(),
            'links'       => [
                'new'        => (int) $links->new,
                'lost'       => (int) $links->lost,
                'broken'     => (int) $links->broken,
                'fetched_at' => $links->fetched_at ? substr($links->fetched_at, 0, 10) : null,
                'days'       => IndexSeoBacklinks::RECENT_DAYS,
            ],
            'competitors' => $competitors->map(fn (SeoCompetitor $competitor) => [
                'domain'  => $competitor->domain,
                'label'   => $competitor->label,
                'summary' => self::summary($competitorLatest->get(StoreSerpResult::normaliseDomain($competitor->domain))),
            ])->all(),
        ];
    }

    private function referringDomains(string $domain): mixed
    {
        $runs     = SeoBacklinkSummary::where('domain', $domain)->orderByDesc('date')->limit(2)->pluck('date');
        $newSince = $runs->count() === 2 ? Carbon::parse($runs->first())->startOfDay() : null;

        $referringDomains = IndexSeoReferringDomains::run($domain, $newSince, SeoBacklinksTabsEnum::REFERRING_DOMAINS->value);

        $referringDomains->getCollection()->each(fn (SeoReferringDomain $referringDomain) => $referringDomain->setAttribute(
            'is_new',
            $newSince && $referringDomain->lost_at === null && $referringDomain->first_fetched_at->gte($newSince)
        ));

        return SeoReferringDomainResource::collection($referringDomains);
    }

    private function gap(Shop $shop, ActionRequest $request): array
    {
        $domains = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('gap_domains', '')))));
        $result  = null;
        $error   = null;

        if ($domains) {
            try {
                $result = GetBacklinkGap::run($shop->website, $domains);
            } catch (ValidationException $e) {
                $error = Arr::first(Arr::flatten($e->errors()));
            }
        }

        return [
            'competitors' => $shop->seoCompetitors()->orderBy('domain')->pluck('domain')->all(),
            'domains'     => $domains,
            'max'         => GetBacklinkGap::MAX_DOMAINS,
            'result'      => $result,
            'error'       => $error,
        ];
    }

    private function tabProp(SeoBacklinksTabsEnum $tab, Closure $resolver): mixed
    {
        return $this->tab === $tab->value ? $resolver : Inertia::optional($resolver);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title  = __('Backlinks');
        $domain = $shop->website ? StoreSerpResult::normaliseDomain($shop->website->domain) : null;

        return Inertia::render(
            'Org/Web/SeoBacklinks',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => $title,
                'pageHead'     => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-external-link-alt'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => SeoBacklinksTabsEnum::navigation(),
                ],
                'domain'       => $domain,
                'isConfigured' => DataForSeoClient::make() !== null,
                'fetchRoute'   => app()->environment('local') && $this->canEdit && $shop->website ? [
                    'name'       => 'grp.models.shop.seo.backlink_fetch.store',
                    'parameters' => [$shop->id],
                ] : null,

                SeoBacklinksTabsEnum::OVERVIEW->value => $this->tabProp(
                    SeoBacklinksTabsEnum::OVERVIEW,
                    fn () => $domain ? $this->overview($shop, $domain) : null
                ),

                SeoBacklinksTabsEnum::REFERRING_DOMAINS->value => $this->tabProp(
                    SeoBacklinksTabsEnum::REFERRING_DOMAINS,
                    fn () => $domain ? $this->referringDomains($domain) : null
                ),

                SeoBacklinksTabsEnum::BACKLINKS->value => $this->tabProp(
                    SeoBacklinksTabsEnum::BACKLINKS,
                    fn () => $shop->website ? SeoBacklinkResource::collection(IndexSeoBacklinks::run($shop->website, SeoBacklinksTabsEnum::BACKLINKS->value)) : null
                ),

                SeoBacklinksTabsEnum::GAP->value => $this->tabProp(
                    SeoBacklinksTabsEnum::GAP,
                    fn () => $shop->website ? $this->gap($shop, $request) : null
                ),
            ]
        )->table(IndexSeoReferringDomains::make()->tableStructure(prefix: SeoBacklinksTabsEnum::REFERRING_DOMAINS->value))
            ->table(IndexSeoBacklinks::make()->tableStructure(prefix: SeoBacklinksTabsEnum::BACKLINKS->value));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $shopParameters = Arr::only($routeParameters, ['organisation', 'shop']);

        return array_merge(
            ShowSeoDashboard::make()->getBreadcrumbs($shopParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.backlinks.show',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('Backlinks'),
                    ],
                ],
            ]
        );
    }
}

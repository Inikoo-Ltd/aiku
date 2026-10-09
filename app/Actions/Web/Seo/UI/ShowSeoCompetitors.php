<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Seo\GetDomainComparison;
use App\Actions\Web\Seo\GetKeywordGap;
use App\Actions\Web\Seo\GetOrganicCompetitors;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Enums\UI\Web\SeoCompetitorsTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoCompetitor;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoCompetitors extends OrgAction
{
    use WithWebAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoCompetitorsTabsEnum::values());

        return $shop;
    }

    private function domains(Shop $shop): array
    {
        $suggestions = [];
        $error       = null;

        try {
            $suggestions = GetOrganicCompetitors::run($shop);
        } catch (ValidationException $e) {
            $error = Arr::first(Arr::flatten($e->errors()));
        } catch (DataForSeoException $e) {
            $error = $e->getMessage();
        }

        return [
            'competitors'       => $shop->seoCompetitors()
                ->orderBy('domain')
                ->get()
                ->map(fn (SeoCompetitor $competitor) => [
                    'id'           => $competitor->id,
                    'domain'       => $competitor->domain,
                    'label'        => $competitor->label,
                    'delete_route' => [
                        'name'       => 'grp.models.seo_competitor.delete',
                        'parameters' => [$competitor->id],
                        'method'     => 'delete',
                    ],
                ])
                ->all(),
            'suggestions'       => $suggestions,
            'suggestions_error' => $error,
        ];
    }

    /**
     * @param  callable(array<int, string>, bool): mixed  $tool
     */
    private function tool(Shop $shop, ActionRequest $request, string $parameter, bool $runsWithoutDomains, callable $tool): array
    {
        $competitors = $shop->seoCompetitors()->orderBy('domain')->pluck('domain')->all();
        $requested   = array_values(array_filter(array_map('trim', explode(',', (string) $request->query($parameter, '')))));
        $domains     = $requested ?: array_slice($competitors, 0, GetDomainComparison::MAX_DOMAINS);
        $result      = null;
        $error       = null;

        if ($requested || ($runsWithoutDomains && $domains)) {
            try {
                $result = $tool($domains, $requested !== []);
            } catch (ValidationException $e) {
                $error = Arr::first(Arr::flatten($e->errors()));
            } catch (DataForSeoException $e) {
                $error = $e->getMessage();
            }
        }

        return [
            'competitors' => $competitors,
            'domains'     => $domains,
            'max'         => GetDomainComparison::MAX_DOMAINS,
            'parameter'   => $parameter,
            'result'      => $result,
            'error'       => $error,
        ];
    }

    private function keywordGap(Shop $shop, ActionRequest $request): array
    {
        $languageCode = strtolower($shop->language->code);

        return [
            ...$this->tool($shop, $request, 'gap_domains', false, fn (array $domains) => GetKeywordGap::run($shop, $domains)),
            'tracked'    => $shop->seoTrackedKeywords()
                ->where('country_code', $shop->country->code)
                ->where('language_code', $languageCode)
                ->pluck('keyword')
                ->unique()
                ->values()
                ->all(),
            'trackRoute' => $this->canEdit ? [
                'name'       => 'grp.models.shop.seo.tracked_keyword.store',
                'parameters' => [$shop->id],
            ] : null,
            'market'     => [
                'country_code'  => $shop->country->code,
                'language_code' => $languageCode,
            ],
        ];
    }

    private function tabProp(SeoCompetitorsTabsEnum $tab, Closure $resolver): mixed
    {
        return $this->tab === $tab->value ? $resolver : Inertia::optional($resolver);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title     = __('Competitors');
        $isUsable  = $shop->website && $shop->country && $shop->language;

        return Inertia::render(
            'Org/Web/SeoCompetitors',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => $title,
                'pageHead'     => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-users'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => SeoCompetitorsTabsEnum::navigation(),
                ],
                'canEdit'      => $this->canEdit,
                'isUsable'     => (bool) $isUsable,
                'isConfigured' => DataForSeoClient::make() !== null,
                'addRoute'     => [
                    'name'       => 'grp.models.shop.seo.competitor.store',
                    'parameters' => [$shop->id],
                ],
                'market'       => $isUsable ? [
                    'domain'   => $shop->website->domain,
                    'country'  => $shop->country->name,
                    'language' => $shop->language->name,
                ] : null,

                SeoCompetitorsTabsEnum::DOMAINS->value => $this->tabProp(
                    SeoCompetitorsTabsEnum::DOMAINS,
                    fn () => $this->domains($shop)
                ),

                SeoCompetitorsTabsEnum::COMPARISON->value => $this->tabProp(
                    SeoCompetitorsTabsEnum::COMPARISON,
                    fn () => $isUsable ? $this->tool($shop, $request, 'compare_domains', true, fn (array $domains, bool $fetchMissing) => GetDomainComparison::run($shop, $domains, $fetchMissing)) : null
                ),

                SeoCompetitorsTabsEnum::KEYWORD_GAP->value => $this->tabProp(
                    SeoCompetitorsTabsEnum::KEYWORD_GAP,
                    fn () => $isUsable ? $this->keywordGap($shop, $request) : null
                ),
            ]
        );
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
                            'name'       => 'grp.org.shops.show.seo.competitors.show',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('Competitors'),
                    ],
                ],
            ]
        );
    }
}

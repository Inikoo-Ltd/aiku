<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Enums\UI\Web\SeoKeywordsTabsEnum;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Http\Resources\Web\SeoTrackedKeywordResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoCompetitor;
use Closure;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoKeywords extends OrgAction
{
    use WithWebAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoKeywordsTabsEnum::values());

        return $shop;
    }

    private function competitors(Shop $shop): array
    {
        return $shop->seoCompetitors()
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
            ->all();
    }

    private function tabProp(SeoKeywordsTabsEnum $tab, Closure $resolver): mixed
    {
        return $this->tab === $tab->value ? $resolver : Inertia::optional($resolver);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title = __('Keywords');

        return Inertia::render(
            'Org/Web/SeoKeywords',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-key'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SeoKeywordsTabsEnum::navigation(),
                ],
                'canEdit'     => $this->canEdit,
                'routes'      => [
                    'track'          => [
                        'name'       => 'grp.models.shop.seo.tracked_keyword.store',
                        'parameters' => [$shop->id],
                        'method'     => 'post',
                    ],
                    'add_competitor' => [
                        'name'       => 'grp.models.shop.seo.competitor.store',
                        'parameters' => [$shop->id],
                        'method'     => 'post',
                    ],
                ],
                'options'     => [
                    'countries'   => Country::orderBy('name')->get(['code', 'name'])->map(fn (Country $country) => ['value' => $country->code, 'label' => $country->name])->all(),
                    'languages'   => Language::orderBy('name')->get(['code', 'name'])->map(fn (Language $language) => ['value' => $language->code, 'label' => $language->name])->all(),
                    'devices'     => collect(SeoKeywordDeviceEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                    'frequencies' => collect(SeoKeywordFrequencyEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                ],
                'defaults'    => [
                    'country_code'  => $shop->country?->code,
                    'language_code' => $shop->language?->code,
                ],

                SeoKeywordsTabsEnum::TRACKED_KEYWORDS->value => $this->tabProp(
                    SeoKeywordsTabsEnum::TRACKED_KEYWORDS,
                    fn () => SeoTrackedKeywordResource::collection(IndexSeoTrackedKeywords::run($shop, SeoKeywordsTabsEnum::TRACKED_KEYWORDS->value))
                ),

                SeoKeywordsTabsEnum::COMPETITORS->value => $this->tabProp(
                    SeoKeywordsTabsEnum::COMPETITORS,
                    fn () => $this->competitors($shop)
                ),
            ]
        )->table(IndexSeoTrackedKeywords::make()->tableStructure(prefix: SeoKeywordsTabsEnum::TRACKED_KEYWORDS->value));
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
                            'name'       => 'grp.org.shops.show.seo.keywords.show',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('Keywords'),
                    ],
                ],
            ]
        );
    }
}

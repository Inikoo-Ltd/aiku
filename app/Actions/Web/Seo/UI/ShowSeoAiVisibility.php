<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Seo\GetSeoAiVisibility;
use App\Actions\Web\Seo\StoreSeoAiAnswer;
use App\Actions\Web\Seo\UpdateSeoAiBrandNames;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Enums\UI\Web\SeoAiVisibilityTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Organisation;
use App\Services\DataForSeo\DataForSeoClient;
use Closure;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoAiVisibility extends OrgAction
{
    use WithWebAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoAiVisibilityTabsEnum::values());

        return $shop;
    }

    private function prompts(Shop $shop): array
    {
        return [
            'prompts'          => GetSeoAiVisibility::make()->prompts($shop),
            'days'             => GetSeoAiVisibility::DAYS,
            'brandNames'       => StoreSeoAiAnswer::brandNames($shop),
            'maxBrandNames'    => UpdateSeoAiBrandNames::MAX_NAMES,
            'brandNamesRoute'  => [
                'name'       => 'grp.models.shop.seo.ai_brand_names.update',
                'parameters' => [$shop->id],
                'method'     => 'patch',
            ],
            'storeRoute'       => [
                'name'       => 'grp.models.shop.seo.ai_prompt.store',
                'parameters' => [$shop->id],
            ],
            'defaults'         => [
                'country_code'  => $shop->country?->code,
                'language_code' => $shop->language?->code,
            ],
            'options'          => [
                'countries' => Country::orderBy('name')->get(['code', 'name'])->map(fn (Country $country) => ['value' => $country->code, 'label' => $country->name])->all(),
                'languages' => Language::orderBy('name')->get(['code', 'name'])->map(fn (Language $language) => ['value' => $language->code, 'label' => $language->name])->all(),
            ],
        ];
    }

    private function tabProp(SeoAiVisibilityTabsEnum $tab, Closure $resolver): mixed
    {
        return $this->tab === $tab->value ? $resolver : Inertia::optional($resolver);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title = __('AI visibility');

        return Inertia::render(
            'Org/Web/SeoAiVisibility',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => $title,
                'pageHead'     => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => SeoAiVisibilityTabsEnum::navigation(),
                ],
                'canEdit'      => $this->canEdit,
                'isUsable'     => (bool) $shop->website,
                'isConfigured' => DataForSeoClient::make() !== null,
                'runRoute'     => app()->environment('local') && $this->canEdit ? [
                    'name'       => 'grp.models.shop.seo.ai_visibility_run.store',
                    'parameters' => [$shop->id],
                ] : null,

                SeoAiVisibilityTabsEnum::OVERVIEW->value => $this->tabProp(
                    SeoAiVisibilityTabsEnum::OVERVIEW,
                    fn () => GetSeoAiVisibility::make()->overview($shop)
                ),

                SeoAiVisibilityTabsEnum::PROMPTS->value => $this->tabProp(
                    SeoAiVisibilityTabsEnum::PROMPTS,
                    fn () => $this->prompts($shop)
                ),

                SeoAiVisibilityTabsEnum::CITED_PAGES->value => $this->tabProp(
                    SeoAiVisibilityTabsEnum::CITED_PAGES,
                    fn () => GetSeoAiVisibility::make()->citedPages($shop)
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
                            'name'       => 'grp.org.shops.show.seo.ai_visibility.show',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('AI visibility'),
                    ],
                ],
            ]
        );
    }
}

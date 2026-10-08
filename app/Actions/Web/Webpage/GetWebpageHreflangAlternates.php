<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebpageHreflangAlternates
{
    use AsObject;

    /**
     * @return array{locale: string, alternates: array<int, array{hreflang: string, href: string}>}
     */
    public function handle(Webpage $webpage): array
    {
        return [
            'locale'     => $this->ogLocale($webpage->website),
            'alternates' => $this->alternates($webpage),
        ];
    }

    public static function hreflangCode(Website $website): ?string
    {
        $code = data_get($website->settings, 'hreflang.code') ?: $website->shop?->language?->code;

        return $code ? str_replace('_', '-', $code) : null;
    }

    private function ogLocale(Website $website): string
    {
        $code = self::hreflangCode($website) ?? 'en';

        if (str_contains($code, '-')) {
            [$language, $region] = explode('-', $code, 2);
        } else {
            $language = $code;
            $region   = $website->shop?->country?->code ?? 'GB';
        }

        return Str::lower($language).'_'.Str::upper($region);
    }

    /**
     * @return array<int, array{hreflang: string, href: string}>
     */
    private function alternates(Webpage $webpage): array
    {
        $website = $webpage->website;
        $group   = data_get($website->settings, 'hreflang.group');

        if (!$group || $webpage->state !== WebpageStateEnum::LIVE || !$webpage->index_page) {
            return [];
        }

        $websites = Website::query()
            ->where('state', WebsiteStateEnum::LIVE)
            ->where('settings->hreflang->group', $group)
            ->with('shop.language')
            ->get()
            ->keyBy('id');

        if ($websites->count() < 2) {
            return [];
        }

        $webpages = Webpage::query()
            ->whereIn('id', $this->counterpartWebpageIds($webpage, $websites))
            ->whereIn('website_id', $websites->keys())
            ->where('state', WebpageStateEnum::LIVE)
            ->where('index_page', true)
            ->whereNotNull('canonical_url')
            ->get(['id', 'website_id', 'canonical_url'])
            ->sortBy(fn (Webpage $counterpart) => $counterpart->id === $webpage->id ? 0 : 1)
            ->unique('website_id');

        $alternates = [];

        foreach ($webpages as $counterpart) {
            $counterpartWebsite = $websites->get($counterpart->website_id);
            $hreflang           = self::hreflangCode($counterpartWebsite);

            if ($hreflang) {
                $alternates[$hreflang] ??= $counterpart->canonical_url;
            }

            if (data_get($counterpartWebsite->settings, 'hreflang.x_default')) {
                $alternates['x-default'] ??= $counterpart->canonical_url;
            }
        }

        if (count(array_unique($alternates)) < 2) {
            return [];
        }

        return collect($alternates)
            ->map(fn (string $href, string $hreflang) => ['hreflang' => $hreflang, 'href' => $href])
            ->values()
            ->all();
    }

    private function counterpartWebpageIds(Webpage $webpage, Collection $websites): Collection
    {
        $shopIds = $websites->pluck('shop_id');
        $model   = $webpage->model;

        return match (true) {
            $webpage->website->storefront_id === $webpage->id => $websites->pluck('storefront_id')->filter(),
            $model instanceof Product && $model->master_product_id !== null => Product::query()
                ->where('master_product_id', $model->master_product_id)
                ->whereIn('shop_id', $shopIds)
                ->whereNotNull('webpage_id')
                ->pluck('webpage_id'),
            $model instanceof ProductCategory && $model->master_product_category_id !== null => ProductCategory::query()
                ->where('master_product_category_id', $model->master_product_category_id)
                ->whereIn('shop_id', $shopIds)
                ->whereNotNull('webpage_id')
                ->pluck('webpage_id'),
            default => collect([$webpage->id]),
        };
    }
}

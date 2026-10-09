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
use Illuminate\Support\Facades\DB;
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

    /**
     * The live websites sharing the hreflang group of this one, empty when there is no other.
     *
     * @return Collection<int, Website>
     */
    public function groupWebsites(Website $website): Collection
    {
        $group = data_get($website->settings, 'hreflang.group');

        if (!$group) {
            return collect();
        }

        $websites = Website::query()
            ->where('state', WebsiteStateEnum::LIVE)
            ->where('settings->hreflang->group', $group)
            ->with('shop.language')
            ->get()
            ->keyBy('id');

        return $websites->count() < 2 ? collect() : $websites;
    }

    /**
     * Live, indexed webpages of the group's websites whose product or category comes from one of
     * these masters, grouped by master id.
     *
     * @return Collection<int, Collection<int, object{id: int, website_id: int, canonical_url: string}>>
     */
    public function counterpartsByMaster(string $table, string $masterColumn, array $masterIds, Collection $websites, ?string $connection = null): Collection
    {
        if (empty($masterIds) || $websites->isEmpty()) {
            return collect();
        }

        return DB::connection($connection)->table($table)
            ->join('webpages', 'webpages.id', '=', "$table.webpage_id")
            ->whereIn("$table.$masterColumn", $masterIds)
            ->whereIn("$table.shop_id", $websites->pluck('shop_id'))
            ->whereNull("$table.deleted_at")
            ->whereIn('webpages.website_id', $websites->keys())
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->where('webpages.index_page', true)
            ->whereNotNull('webpages.canonical_url')
            ->get(["$table.$masterColumn as master_id", 'webpages.id', 'webpages.website_id', 'webpages.canonical_url'])
            ->groupBy('master_id');
    }

    /**
     * @return Collection<int, object{id: int, website_id: int, canonical_url: string}>
     */
    public function storefrontCounterparts(Collection $websites, ?string $connection = null): Collection
    {
        return DB::connection($connection)->table('webpages')
            ->whereIn('id', $websites->pluck('storefront_id')->filter())
            ->where('state', WebpageStateEnum::LIVE->value)
            ->where('index_page', true)
            ->whereNotNull('canonical_url')
            ->get(['id', 'website_id', 'canonical_url']);
    }

    /**
     * @param  Collection<int, object{id: int, website_id: int, canonical_url: string}>  $counterparts
     *
     * @return array<int, array{hreflang: string, href: string}>
     */
    public function alternatesFor(int $webpageId, Collection $counterparts, Collection $websites): array
    {
        if (!$counterparts->contains('id', $webpageId)) {
            return [];
        }

        $alternates = [];

        $counterparts = $counterparts
            ->sortBy(fn (object $counterpart) => $counterpart->id === $webpageId ? 0 : 1)
            ->unique('website_id');

        foreach ($counterparts as $counterpart) {
            $website = $websites->get($counterpart->website_id);

            if (!$website) {
                continue;
            }

            $hreflang = self::hreflangCode($website);

            if ($hreflang) {
                $alternates[$hreflang] ??= $counterpart->canonical_url;
            }

            if (data_get($website->settings, 'hreflang.x_default')) {
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
        $websites = $this->groupWebsites($webpage->website);

        if ($websites->isEmpty()) {
            return [];
        }

        $model = $webpage->model;

        $counterparts = match (true) {
            $webpage->website->storefront_id === $webpage->id => $this->storefrontCounterparts($websites),
            $model instanceof Product && $model->master_product_id !== null => $this->counterpartsByMaster('products', 'master_product_id', [$model->master_product_id], $websites)
                ->get($model->master_product_id, collect()),
            $model instanceof ProductCategory && $model->master_product_category_id !== null => $this->counterpartsByMaster('product_categories', 'master_product_category_id', [$model->master_product_category_id], $websites)
                ->get($model->master_product_category_id, collect()),
            default => collect(),
        };

        return $this->alternatesFor($webpage->id, $counterparts, $websites);
    }
}

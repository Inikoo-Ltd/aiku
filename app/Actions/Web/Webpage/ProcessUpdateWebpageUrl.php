<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2025 08:25:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Web\Redirect\StoreRedirectFromWebsite;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use Cache;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessUpdateWebpageUrl
{
    use AsAction;


    public function handle(Webpage $webpage, string $oldUrl): void
    {
        $model = $webpage->model;
        if ($model instanceof Product) {
            UpdateProduct::make()->action($model, [
                'url' => $webpage->url,
            ]);
        } elseif ($model instanceof ProductCategory) {
            UpdateProductCategory::make()->action($model, [
                'url' => $webpage->url,
            ]);
        } elseif ($model instanceof Collection) {
            UpdateCollection::make()->action($model, [
                'url' => $webpage->url,
            ]);
        }


        $key = config('iris.cache.webpage_path.prefix').'_'.$webpage->website_id.'_'.strtolower($webpage->url);
        Cache::forget($key);
        $key = config('iris.cache.webpage_path.prefix').'_'.$webpage->website_id.'_'.strtolower($oldUrl);
        Cache::forget($key);

        UpdateWebpageCanonicalUrl::run($webpage);
        BreakWebpageCache::run($webpage);

        if ($webpage->state == WebpageStateEnum::LIVE && $this->needsRedirect($webpage, $oldUrl)) {
            StoreRedirectFromWebsite::make()->action($webpage->website, [
                'from_url' => $oldUrl,
                'to_url'   => $webpage->id,
            ]);
        }
    }

    /**
     * A URL changed only by its slashes ("/page" back to "page") is the same address, and an old
     * address that already redirects keeps its redirect: storing either again fails validation.
     */
    private function needsRedirect(Webpage $webpage, string $oldUrl): bool
    {
        $oldPath = trim($oldUrl, " /");

        if ($oldPath === '' || $oldPath === trim($webpage->url, " /")) {
            return false;
        }

        return !Redirect::where('website_id', $webpage->website_id)
            ->where('from_path', Arr::last(explode('/', $oldPath)))
            ->exists();
    }
}

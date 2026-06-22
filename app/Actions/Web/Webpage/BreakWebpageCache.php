<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 13:03:07 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakWebpageCache extends OrgAction implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(?Webpage $webpage, bool $includeChildren = false): string
    {
        $slug = 'empty';
        if ($webpage) {
            $slug = $webpage->slug;
        }

        return $slug.'-'.$includeChildren ?? 'i';
    }

    public function handle(?Webpage $webpage, bool $includeChildren = false): void
    {
        if (!$webpage) {
            return;
        }

        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_in_'.$webpage->id;
        Cache::forget($key);
        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_out_'.$webpage->id;
        Cache::forget($key);

        BanVarnishWebpage::run($webpage);
        PurgeVarnishWebpageUrl::run($webpage);

        if ($includeChildren && $webpage->model instanceof ProductCategory) {
            /** @var ProductCategory $productCategory */
            $productCategory = $webpage->model;
            foreach ($productCategory->getProducts() as $product) {
                BreakWebpageCache::dispatch($product->webpage, false);
            }
        }
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisation($webpage->organisation, $request);

        $this->handle($webpage);
    }
}

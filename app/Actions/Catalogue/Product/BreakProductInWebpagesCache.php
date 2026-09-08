<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Jun 2025 04:26:48 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Web\Webpage\BanVarnishWebpage;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakProductInWebpagesCache
{
    use AsAction;

    public function handle(Product $product): void
    {
        foreach ($this->getWebpages($product) as $webpage) {
            $this->breakCache($webpage);
        }
    }

    /**
     * Every webpage whose content shows this product: its own page plus the
     * family/department/sub-department listings and any collections it is in.
     *
     * @return Collection<int, Webpage> keyed by webpage id
     */
    public function getWebpages(Product $product): Collection
    {
        return collect([
            $product->webpage,
            $product->family?->webpage,
            $product->department?->webpage,
            $product->subDepartment?->webpage,
        ])
            ->concat($product->containedByCollections->map(fn (\App\Models\Catalogue\Collection $collection) => $collection->webpage)->all())
            ->filter()
            ->keyBy('id');
    }

    public function breakCache(?Webpage $webpage): void
    {
        if ($webpage) {
            $this->forgetCacheKeys($webpage);

            BanVarnishWebpage::run($webpage);
        }

    }

    public function forgetCacheKeys(Webpage $webpage): void
    {
        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_in_'.$webpage->id;
        Cache::forget($key);
        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_out_'.$webpage->id;
        Cache::forget($key);
    }

    protected function commandSignature(): string
    {
        return 'webpages:break_cache {id}';
    }

    protected function asCommand(Command $command): int
    {

        $product = Product::findOrFail($command->argument('id'));
        $this->handle($product);

        return 0;
    }

}

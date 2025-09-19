<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Jun 2025 04:26:48 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Cache;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakProductInWebpagesCache
{
    use AsAction;

    public function handle(Product $product): void
    {
        $this->breakCache($product->webpage);

        if ($product->family && $product->family->webpage) {
            $this->breakCache($product->family->webpage);
        }
        if ($product->department && $product->department->webpage) {
            $this->breakCache($product->department->webpage);
        }
        if ($product->subDepartment && $product->subDepartment->webpage) {
            $this->breakCache($product->subDepartment->webpage);
        }

        foreach ($product->containedByCollections as $collection) {
            $this->breakCache($collection->webpage);
        }

    }

    public function breakCache(?Webpage $webpage): void
    {
        if ($webpage) {
            $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_in_'.$webpage->id;
            Cache::forget($key);
            $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_out_'.$webpage->id;
            Cache::forget($key);
        }

    }

    protected function commandSignature():string
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

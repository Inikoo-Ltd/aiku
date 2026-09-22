<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 17:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Actions\Web\Webpage\StoreProductWebpageDefaultWebBlocks;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class KeepRetiredProductAsSeparate extends OrgAction
{
    use WithCatalogueEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Product $product): Product
    {
        $pagesToRefresh = [];

        $product = DB::transaction(function () use ($product, &$pagesToRefresh) {
            $replacement = Product::find(Arr::get($product->data, 'replaced_by_product_id'));
            $sharedPage  = Webpage::find($product->webpage_id) ?? $this->findWebpageHoldingProductUrl($product);

            $modelData = [
                'is_main'     => true,
                'is_for_sale' => true,
                'data'        => Arr::except($product->data, ['retire_at_cutover', 'replaced_by_product_id']),
            ];

            if ($product->state == ProductStateEnum::DISCONTINUED) {
                $modelData['state']  = ProductStateEnum::ACTIVE;
                $modelData['status'] = ProductStatusEnum::FOR_SALE;
            }

            if ($replacement && $sharedPage && $sharedPage->model_id == $replacement->id && $replacement->webpage_id == $sharedPage->id) {
                $replacementPage = $this->giveReplacementItsOwnWebpage($replacement, $sharedPage);
                $sharedPage->update(['model_id' => $product->id]);
                $modelData['webpage_id'] = $sharedPage->id;
                $modelData['url']        = $sharedPage->url;

                $pagesToRefresh = [$sharedPage, $replacementPage];
            }

            $product = UpdateProduct::make()->action($product, $modelData);

            if (!$product->refresh()->webpage) {
                StoreProductWebpage::make()->action($product);
            }

            return $product->refresh();
        });

        foreach ($pagesToRefresh as $webpage) {
            $this->breakWebsiteCache($webpage->refresh());
        }

        return $product;
    }

    private function findWebpageHoldingProductUrl(Product $product): ?Webpage
    {
        return Webpage::where('website_id', $product->shop->website?->id)
            ->where('url', strtolower($product->code))
            ->first();
    }

    /**
     * @throws \Throwable
     */
    private function giveReplacementItsOwnWebpage(Product $replacement, Webpage $sharedPage): Webpage
    {
        $ownPage = Webpage::where('model_type', 'Product')
            ->where('model_id', $replacement->id)
            ->where('id', '!=', $sharedPage->id)
            ->first();

        if (!$ownPage) {
            $replacement->update(['webpage_id' => null]);

            return $this->publishWithContent(StoreProductWebpage::make()->action($replacement));
        }

        if ($ownPage->state == WebpageStateEnum::CLOSED) {
            ReopenWebpage::run($ownPage);
        }
        $replacement->update([
            'webpage_id' => $ownPage->id,
            'url'        => $ownPage->url,
        ]);

        return $this->publishWithContent($ownPage);
    }

    private function publishWithContent(Webpage $webpage): Webpage
    {
        $isMissingContent = $webpage->webBlocks()->doesntExist();
        if ($isMissingContent) {
            StoreProductWebpageDefaultWebBlocks::run($webpage);
        }

        if ($isMissingContent || !$webpage->refresh()->live_snapshot_id) {
            PublishWebpage::make()->action($webpage->refresh(), ['comment' => 'Kept as a separate product']);
        }

        return $webpage->refresh();
    }

    private function breakWebsiteCache(Webpage $webpage): void
    {
        Cache::forget(config('iris.cache.webpage_path.prefix').'_'.$webpage->website_id.'_'.strtolower($webpage->url));
        BreakWebpageCache::run($webpage);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Product $product, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($product->shop, $request);
        $this->handle($product);

        return back();
    }

    /**
     * @throws \Throwable
     */
    public function action(Product $product): Product
    {
        $this->asAction = true;
        $this->initialisationFromShop($product->shop, []);

        return $this->handle($product);
    }
}

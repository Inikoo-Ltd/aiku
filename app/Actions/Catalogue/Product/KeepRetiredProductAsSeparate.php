<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 17:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class KeepRetiredProductAsSeparate extends OrgAction
{
    use WithCatalogueEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Product $product): Product
    {
        $replacement = Product::find(Arr::get($product->data, 'replaced_by_product_id'));
        $sharedPage  = Webpage::find($product->webpage_id);

        if ($replacement && $sharedPage && $sharedPage->model_id == $replacement->id && $replacement->webpage_id == $sharedPage->id) {
            $this->giveReplacementItsOwnWebpage($replacement, $sharedPage);
            $sharedPage->update(['model_id' => $product->id]);
        }

        $product = UpdateProduct::make()->action($product, [
            'is_main'     => true,
            'is_for_sale' => true,
            'data'        => Arr::except($product->data, ['retire_at_cutover', 'replaced_by_product_id']),
        ]);

        if (!$product->refresh()->webpage) {
            StoreProductWebpage::make()->action($product);
        }

        return $product->refresh();
    }

    /**
     * @throws \Throwable
     */
    private function giveReplacementItsOwnWebpage(Product $replacement, Webpage $sharedPage): void
    {
        $ownPage = Webpage::where('model_type', 'Product')
            ->where('model_id', $replacement->id)
            ->where('id', '!=', $sharedPage->id)
            ->first();

        if (!$ownPage) {
            $replacement->update(['webpage_id' => null]);
            StoreProductWebpage::make()->action($replacement);

            return;
        }

        if ($ownPage->state == WebpageStateEnum::CLOSED) {
            ReopenWebpage::run($ownPage);
        }
        $replacement->update([
            'webpage_id' => $ownPage->id,
            'url'        => $ownPage->url,
        ]);
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

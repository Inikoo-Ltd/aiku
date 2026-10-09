<?php

/*
 * Author: Louis Perez Napitupulu
 * Created: Wed, 07 Oct 2026 09:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Catalogue\Variant;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateVariantProductOrder extends OrgAction
{
    use WithCatalogueEditAuthorisation;

    /**
     * Saving the shop's own order stops it following the master; following again copies the master order back.
     *
     * @param array{follow_master_variant_order?: bool, products?: array<int, array{id: int, index: int}>} $modelData
     */
    public function handle(Variant $variant, array $modelData): Variant
    {
        DB::transaction(function () use ($variant, $modelData) {
            if (Arr::get($modelData, 'follow_master_variant_order')) {
                $variant->update(['follow_master_variant_order' => true]);
                CopyMasterVariantProductOrder::run($variant);

                return;
            }

            foreach (Arr::get($modelData, 'products', []) as $product) {
                Product::where('id', $product['id'])
                    ->where('variant_id', $variant->id)
                    ->update(['index_under_variant' => $product['index']]);
            }

            $variant->update(['follow_master_variant_order' => false]);
        });

        return $variant;
    }

    public function rules(): array
    {
        return [
            'follow_master_variant_order' => ['sometimes', 'boolean'],
            'products'                    => ['required_without:follow_master_variant_order', 'array'],
            'products.*.id'               => ['required', 'integer'],
            'products.*.index'            => ['required', 'integer', 'gte:0'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(Variant $variant, ActionRequest $request): Variant
    {
        $this->shop = $variant->shop;
        $this->initialisationFromShop($variant->shop, $request);

        return $this->handle($variant, $this->validatedData);
    }
}

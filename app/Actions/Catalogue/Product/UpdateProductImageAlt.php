<?php

/*
 * Author: Rifqi Taufiqurrohman <rifqitaufiqurrohman1@gmail.com>
 * Created: Thu, 07 May 2026 Asia/Jakarta
 * Copyright (c) 2026, Inikoo
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductImageAlt extends OrgAction
{
    public function handle(Product $product, Media $media, array $modelData): Product
    {
        $product->images()->updateExistingPivot($media->id, [
            'caption' => $modelData['alt'] ?? null,
        ]);

        return $product;
    }

    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function asController(Product $product, Media $media, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product, $media, $this->validatedData);
    }
}

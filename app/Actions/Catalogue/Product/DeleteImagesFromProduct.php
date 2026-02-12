<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 15:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\UI\GetProductShowcase;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithImageColumns;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeleteImagesFromProduct extends OrgAction
{
    use WithWebAuthorisation;
    use WithImageColumns;

    public function handle(Product $product, Media $media): Product
    {
        if ($product->is_single_trade_unit) {
            return $product;
        }

        $product->images()->detach($media->id);

        $updateData = [];

        foreach ($this->imageColumns() as $column) {
            if ($product->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $product->update($updateData);
        }


        return $product;
    }


    public function jsonResponse(Product $product): array
    {
        return GetProductShowcase::run($product);
    }

    public function asController(Organisation $organisation, Product $product, Media $media, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);
        $this->handle($product, $media);
    }
}

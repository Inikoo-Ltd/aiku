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
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeleteImagesFromProduct extends OrgAction
{
    use WithWebAuthorisation;

    public function handle(Product $product, Media $media): Product
    {
        $product->images()->detach($media->id);

        return $product;
    }

    public function jsonResponse(Product $product): array
    {
        return GetProductShowcase::run($product);
    }

    public function asController(Organisation $organisation, Product $product, Media $media, ActionRequest $request): void
    {
        $this->scope = $organisation;
        $this->initialisation($organisation, $request);

        $this->handle($product, $media);
    }
}

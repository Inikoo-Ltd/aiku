<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplierProductImageEditAuthorisation;
use App\Models\Helpers\Media;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromSupplierProduct extends OrgAction
{
    use WithSupplierProductImageEditAuthorisation;

    public function handle(SupplierProduct $supplierProduct, Media $media): SupplierProduct
    {
        $supplierProduct->images()->detach($media->id);

        if ($supplierProduct->image_id == $media->id) {
            $supplierProduct->update(['image_id' => $supplierProduct->images()->value('media.id')]);
        }

        return $supplierProduct;
    }

    public function asController(SupplierProduct $supplierProduct, Media $media, ActionRequest $request): void
    {
        $this->initialisationFromGroup($supplierProduct->group, $request);

        $this->handle($supplierProduct, $media);
    }

    public function inOrgSupplierProduct(OrgSupplierProduct $orgSupplierProduct, Media $media, ActionRequest $request): void
    {
        $this->initialisation($orgSupplierProduct->organisation, $request);

        $this->handle($orgSupplierProduct->supplierProduct, $media);
    }
}

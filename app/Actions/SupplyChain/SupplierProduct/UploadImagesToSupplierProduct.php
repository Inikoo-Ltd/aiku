<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplierProductImageEditAuthorisation;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Helpers\Media;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\ActionRequest;

/**
 * Pictures for internal use (warehouse, packing, procurement). They live on the supplier product only,
 * never on its trade units, so they never reach products or websites.
 */
class UploadImagesToSupplierProduct extends OrgAction
{
    use WithSupplierProductImageEditAuthorisation;
    use WithUploadModelImages;

    /**
     * @return array<int, Media>
     */
    public function handle(SupplierProduct $supplierProduct, array $modelData): array
    {
        $medias = $this->uploadImages($supplierProduct, 'image', $modelData);

        if (!$supplierProduct->image_id && !empty($medias)) {
            $supplierProduct->update(['image_id' => $medias[0]->id]);
        }

        return $medias;
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): void
    {
        $this->initialisationFromGroup($supplierProduct->group, $request);

        $this->handle($supplierProduct, $this->validatedData);
    }

    public function inOrgSupplierProduct(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): void
    {
        $this->initialisation($orgSupplierProduct->organisation, $request);

        $this->handle($orgSupplierProduct->supplierProduct, $this->validatedData);
    }
}

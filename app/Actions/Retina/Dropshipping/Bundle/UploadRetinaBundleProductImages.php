<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Catalogue\Product\UploadImagesToProduct;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Bundle;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Traits\SanitizeInputs;
use Lorisleiva\Actions\ActionRequest;

class UploadRetinaBundleProductImages extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Product $product, array $modelData): Bundle
    {
        return UploadImagesToProduct::run($product, 'image', $modelData);
    }

    public function rules(): array
    {
        return UploadImagesToProduct::make()->rules();
    }

    public function asController(Bundle $bundle, Product $product, ActionRequest $request): Bundle
    {
        $this->enableSanitize();
        $this->customerSalesChannel = $bundle->customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($product, $this->validatedData);
    }
}

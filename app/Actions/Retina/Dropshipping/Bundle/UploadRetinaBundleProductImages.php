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
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Bundle;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UploadRetinaBundleProductImages extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Product $product, array $modelData): array
    {
        return UploadImagesToProduct::run($product, 'image', $modelData);
    }

    public function rules(): array
    {
        return UploadImagesToProduct::make()->rules();
    }


    public function jsonResponse(array $images): ImageResource
    {
        return ImageResource::make(Arr::first($images));
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Product $product, ActionRequest $request): array
    {
        $this->enableSanitize();
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($product, $this->validatedData);
    }
}

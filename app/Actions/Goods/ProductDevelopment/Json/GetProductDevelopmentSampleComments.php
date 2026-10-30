<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-14h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Json;

use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentComments;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentSample;
use Lorisleiva\Actions\ActionRequest;

/**
 * The discussion on one sample of an in-house SKU, shown in the Formulation & samples tab.
 */
class GetProductDevelopmentSampleComments extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, ProductDevelopmentSample $sample): array
    {
        abort_unless($sample->product_development_id === $productDevelopment->id, 404);

        return GetProductDevelopmentComments::run($productDevelopment, null, $sample);
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(ProductDevelopment $productDevelopment, ProductDevelopmentSample $sample, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($productDevelopment, $sample);
    }
}

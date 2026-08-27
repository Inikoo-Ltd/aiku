<?php

/*
 * author Yudhistira Adhiwiguna
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;

/**
 * Serializes a family with the same resource Iris uses to render the family description
 * blocks, so the workshop preview shows the full data instead of the trimmed picker payload.
 */
class GetFamilyDescriptionDataInWorkshop extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function asController(ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($family->shop, $request);

        return $this->handle($family);
    }

    public function handle(ProductCategory $family): ProductCategory
    {
        return $family;
    }

    public function jsonResponse(ProductCategory $family): array
    {
        return [
            'data' => WebBlockFamilyResource::make($family)->toArray(request()),
        ];
    }
}

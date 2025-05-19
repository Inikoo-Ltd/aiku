<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 15:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class AttachImagesToProduct extends OrgAction
{
    use WithWebAuthorisation;
    use WithAttachMediaToModel;

    public function handle(Product $product, $scope, array $modelData)
    {
        foreach ($modelData['images'] as $image) {
            $media = Media::find($image);
            $this->attachMediaToModel($product, $media, $scope);
        }

        return $product;
    }

    public function rules(): array
    {
        return [
            'images'   => ['required', 'array'],
        ];
    }

    public function jsonResponse($medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }

    public function asController(Organisation $organisation, Product $product, ActionRequest $request)
    {
        $this->scope = $organisation;
        $this->initialisation($organisation, $request);

        $this->handle($product, 'image', $this->validatedData);
    }
}

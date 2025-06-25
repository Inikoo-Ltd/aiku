<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 11:51:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Catalogue\Product;

trait WithUploadProductImage
{
    use WithWebAuthorisation;
    use WithAttachMediaToModel;

    public function handle(Product $model, string $scope, array $modelData): array
    {
        $medias = [];

        foreach ($modelData['images'] as $imageFile) {

            $imageData = [
                'path'         => $imageFile->getPathName(),
                'originalName' => $imageFile->getClientOriginalName(),
                'extension'    => $imageFile->guessClientExtension(),
                'checksum'     => md5_file($imageFile->getPathName())
            ];

            $media = StoreMediaFromFile::run($model, $imageData, $scope);
            $this->attachMediaToModel($model, $media, $scope);
            $medias[] = $media;

        }

        return $medias;
    }

    public function rules(): array
    {
        return [
            'images'   => ['required', 'array'],
            'images.*' => ["mimes:jpg,png,jpeg,gif", "max:50000"]
        ];
    }


}

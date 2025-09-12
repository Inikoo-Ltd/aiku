<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:11:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:12:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Generic helper to upload and attach images to any Eloquent model
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use Illuminate\Database\Eloquent\Model;

trait WithUploadModelImages
{
    use WithAttachMediaToModel;

    /**
     * Process uploaded image files and attach them to the provided model.
     *
     * @param Model $model  Eloquent model to attach media to
     * @param string $scope Media collection/scope name
     * @param array $modelData Validated request data containing 'images' array
     * @return array List of created Media models
     */
    public function uploadImages(Model $model, string $scope, array $modelData): array
    {
        $medias = [];

        foreach ($modelData['images'] as $imageFile) {
            $imageData = [
                'path'         => $imageFile->getPathName(),
                'originalName' => $imageFile->getClientOriginalName(),
                'extension'    => $imageFile->guessClientExtension(),
                'checksum'     => md5_file($imageFile->getPathName()),
            ];

            $media = StoreMediaFromFile::run($model, $imageData, $scope);
            $this->attachMediaToModel($model, $media, $scope);
            $medias[] = $media;
        }

        return $medias;
    }

    /**
     * Standard validation rules for image uploads.
     */
    public function imageUploadRules(): array
    {
        return [
            'images'   => ['required', 'array'],
            'images.*' => ["mimes:jpg,png,jpeg,gif", "max:50000"],
        ];
    }
}

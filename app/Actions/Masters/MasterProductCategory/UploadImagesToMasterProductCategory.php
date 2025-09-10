<?php

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToMasterProductCategory extends GrpAction
{
    use WithAttachMediaToModel;

    public function handle(MasterProductCategory $model, string $scope, array $modelData): array
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

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, 'image', $this->validatedData);
    }
}

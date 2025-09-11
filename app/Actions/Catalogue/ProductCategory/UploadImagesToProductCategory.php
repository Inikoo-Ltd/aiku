<?php

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToProductCategory extends OrgAction
{
    use WithAttachMediaToModel;

    public function handle(ProductCategory $model, string $scope, array $modelData): array
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

    public function asController(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, 'image', $this->validatedData);
    }
}

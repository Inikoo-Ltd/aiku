<?php

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToTradeUnit extends GrpAction
{
    use WithAttachMediaToModel;

    public function handle(TradeUnit $model, string $scope, array $modelData): array
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

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, 'image', $this->validatedData);
    }
}

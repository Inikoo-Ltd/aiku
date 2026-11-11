<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 11:51:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Helpers\Media\UpdateIsAnimatedMedia;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\SysAdmin\Group;
use App\Models\Web\Announcement;
use App\Models\Web\WebBlock;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Closure;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

trait WithUploadWebImage
{
    use WithAttachMediaToModel;
    public function handle(WebBlock|Website|Group|Announcement|WebsiteDialog $model, string $scope, array $modelData): Collection
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


        return collect($medias);
    }

    public const int MAX_ANIMATED_GIF_KB = 300;

    public function rules(): array
    {
        return [
            'images'   => ['required'],
            'images.*' => ["mimes:jpg,png,jpeg,gif,avif,webp", "max:50000", $this->animatedGifSizeRule()],
        ];
    }

    private function animatedGifSizeRule(): Closure
    {
        return function (string $attribute, mixed $file, Closure $fail): void {
            if (!$file instanceof UploadedFile
                || $file->getMimeType() !== 'image/gif'
                || $file->getSize() <= self::MAX_ANIMATED_GIF_KB * 1024
                || !UpdateIsAnimatedMedia::make()->isGifAnimated(fopen($file->getPathname(), 'r'))) {
                return;
            }

            $fail(__('Animated GIF :name is :size KB, the most a website animation can be is :max KB. Make it smaller (fewer frames, smaller size) or use a video.', [
                'name' => $file->getClientOriginalName(),
                'size' => (int) ceil($file->getSize() / 1024),
                'max'  => self::MAX_ANIMATED_GIF_KB,
            ]));
        };
    }

    public function jsonResponse($medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }
}

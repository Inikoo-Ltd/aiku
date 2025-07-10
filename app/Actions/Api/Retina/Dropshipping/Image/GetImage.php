<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Image;

use App\Actions\RetinaApiAction;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Media;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetImage extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Media $image): Media
    {
        return $image;
    }

    public function asController(Media $image, ActionRequest $request)
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($image);
    }


    public function jsonResponse(Media $image)
    {
        $relativePath = ltrim(str_replace(storage_path('media'), '', $image->getPath()), '/');

        return response()->file(
            Storage::disk($image->disk)->path($relativePath),
            [
                'Content-Type' => $image->mime_type,
                'Content-Disposition' => 'inline; filename="' . $image->name . '"',
            ]
        );
    }
}

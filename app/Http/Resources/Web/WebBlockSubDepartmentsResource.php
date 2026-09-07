<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:14:45 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property mixed $web_images
 * @property mixed $image_id
 * @property mixed $canonical_url
 */
class WebBlockSubDepartmentsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $webImages    = json_decode(trim($this->web_images, '"'), true) ?? [];
        $imageSources = null;
        $media        = Media::find($this->image_id);

        if ($media) {
            $imageSources = GetPictureSources::run($media->getImage()->resize(720, 720));
        }

        return [
            'slug'        => $this->slug,
            'code'        => $this->code,
            'name'        => $this->name,
            'description' => $this->description,
            'url'         => $this->canonical_url,
            'web_images'  => $webImages,
            'image'       => $imageSources,
        ];
    }
}

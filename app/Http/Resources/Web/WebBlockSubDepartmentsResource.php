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
 * @property mixed $web_images
 * @property mixed $department_url
 * @property mixed $url
 */
class WebBlockSubDepartmentsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {

        $url = $this->department_url;
        if ($url) {
            $url .= '/';
        }
        $url = $url.$this->url;


        $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];

        return [
            'slug'       => $this->slug,
            'code'       => $this->code,
            'name'       => $this->name,
            'url'        => $url,
            'web_images' => $webImages,
        ];
    }
}

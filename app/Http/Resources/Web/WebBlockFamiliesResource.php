<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 14:54:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $web_images
 * @property mixed $code
 * @property mixed $name
 * @property mixed $title
 * @property mixed $url
 * @property mixed $parent_url
 * @property mixed $canonical_url
 */
class WebBlockFamiliesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];

        return [
            'code' => $this->code,
            'name' => $this->name,
            'title' => $this->title,
            'url' => $this->canonical_url,
            'web_images' => $webImages,
        ];
    }
}

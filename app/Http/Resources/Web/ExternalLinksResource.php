<?php

/*
 * author Arya Permana - Kirin
 * created on 21-10-2024-09h-38m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalLinksResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'number_websites_shown' => $this->number_websites_shown,
            'number_webpages_shown' => $this->number_webpages_shown,
            'number_web_blocks_shown' => $this->number_web_blocks_shown,
            'number_websites_hidden' => $this->number_websites_hidden,
            'number_webpages_hidden' => $this->number_webpages_hidden,
            'number_web_blocks_hidden' => $this->number_web_blocks_hidden,
            'status' => $this->status,
        ];
    }
}

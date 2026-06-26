<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-11h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $url
 * @property mixed $type
 * @property mixed $to_webpage_title
 * @property mixed $to_webpage_code
 * @property mixed $to_webpage_slug
 * @property mixed $path
 */
class RedirectsResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id'               => $this->id,
            'url'              => $this->url,
            'type'             => $this->type,
            'path'             => '…/'.$this->path,
            'full_path'        => $this->full_path,
            'to_webpage_title' => $this->to_webpage_title,
            'to_webpage_code'  => $this->to_webpage_code,
            'to_webpage_slug'  => $this->to_webpage_slug,
        ];
    }
}

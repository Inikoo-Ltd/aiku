<?php

/*
 * author Louis Perez
 * created on 25-02-2026-13h-39m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\WebLayoutTemplate;
use Illuminate\Http\Resources\Json\JsonResource;

class WebLayoutTemplateResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var WebLayoutTemplate $webLayoutTemplate */
        $webLayoutTemplate = $this;

        return [
            'id'                => $webLayoutTemplate->id,
            'label'             => $webLayoutTemplate->label,
            'author'            => $webLayoutTemplate->author_name,
            'template_data'     => $webLayoutTemplate->data,
            'created_at'        => $webLayoutTemplate->created_at,
            'updated_at'        => $webLayoutTemplate->updated_at,
        ];
    }
}

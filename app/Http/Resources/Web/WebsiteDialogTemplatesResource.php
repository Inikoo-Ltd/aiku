<?php

namespace App\Http\Resources\Web;

use App\Models\Web\WebsiteDialogTemplate;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteDialogTemplatesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var WebsiteDialogTemplate $template */
        $template = $this->resource;

        return [
            'id'                   => $template->id,
            'code'                 => $template->code,
            'name'                 => $template->name,
            'component'            => $template->component,
            'fields'               => $template->data['fields'] ?? [],
            'container_properties' => $template->data['container_properties'] ?? [],
        ];
    }
}

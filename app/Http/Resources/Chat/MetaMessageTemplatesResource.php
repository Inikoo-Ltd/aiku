<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Chat;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $template_id
 * @property string $name
 * @property string|null $language
 * @property string|null $status
 * @property string|null $category
 * @property mixed $synchronize_at
 */
class MetaMessageTemplatesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'template_id'    => $this->template_id,
            'name'           => $this->name,
            'language'       => $this->language,
            'status'         => $this->status,
            'category'       => $this->category,
            'synchronize_at' => $this->synchronize_at,
        ];
    }
}

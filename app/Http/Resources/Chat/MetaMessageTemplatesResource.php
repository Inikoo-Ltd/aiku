<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Chat;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
        $body = Arr::get(
            collect(Arr::get($this->data, 'components', []))->firstWhere('type', 'BODY') ?? [],
            'text',
            ''
        );

        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        return [
            'id'             => $this->id,
            'template_id'    => $this->template_id,
            'name'           => $this->name,
            'label'          => Arr::get($this->data ?? [], 'label'),
            'language'       => $this->language,
            'status'         => $this->status,
            'is_draft'       => blank($this->template_id),
            'category'       => $this->category,
            'synchronize_at' => $this->synchronize_at,
            'body'           => $body,
            'variable_count' => empty($matches[1]) ? 0 : max(array_map('intval', $matches[1])),
            'merge_tags'     => Arr::get($this->data ?? [], 'merge_tags.body', []),
        ];
    }
}

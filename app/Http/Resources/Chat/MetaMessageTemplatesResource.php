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

        $components = collect(Arr::get($this->data, 'components', []));
        $header     = $components->firstWhere('type', 'HEADER');
        $buttons    = Arr::get($components->firstWhere('type', 'BUTTONS') ?? [], 'buttons', []);

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

            /* Feeds the hover preview on the list, so the whole message can be judged
               without opening the template. */
            'header'         => $header ? [
                'format' => Arr::get($header, 'format'),
                'text'   => Arr::get($header, 'text'),
            ] : null,
            'footer'         => Arr::get($components->firstWhere('type', 'FOOTER') ?? [], 'text'),
            'buttons'        => array_map(fn ($button) => [
                'type' => Arr::get($button, 'type'),
                'text' => Arr::get($button, 'text'),
            ], $buttons),
            'media_preview'  => $this->headerMedia?->getUrl(),
        ];
    }
}

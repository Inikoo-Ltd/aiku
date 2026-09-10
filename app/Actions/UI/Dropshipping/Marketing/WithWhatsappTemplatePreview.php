<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Models\Chat\MetaMessageTemplate;
use Illuminate\Support\Arr;

trait WithWhatsappTemplatePreview
{
    /**
     * Flattens Meta's component array into the shape the preview bubble renders.
     *
     * mergeTags rides along because a template's tags decide who it can reach: the workshop
     * compares them across a template swap, and reads them from the same path the send path
     * fills them from.
     *
     * @return array{value: int, label: string, language: string, header: array|null, body: string|null, footer: string|null, buttons: array, mergeTags: array<int, string>}
     */
    protected function whatsappTemplatePreview(MetaMessageTemplate $template): array
    {
        $components = Arr::get($template->data, 'components', []);

        $componentOf = fn (string $type) => Arr::first(
            $components,
            fn ($component) => Arr::get($component, 'type') === $type
        );

        $header = $componentOf('HEADER');

        return [
            'value'    => $template->id,
            'label'    => $template->name,
            'language' => $template->language,
            'header'   => $header ? [
                'format' => Arr::get($header, 'format', 'TEXT'),
                'text'   => Arr::get($header, 'text'),
            ] : null,
            'body'     => Arr::get($componentOf('BODY'), 'text'),
            'footer'   => Arr::get($componentOf('FOOTER'), 'text'),
            'buttons'  => Arr::get($componentOf('BUTTONS'), 'buttons', []),
            'mergeTags' => Arr::get($template->data, 'merge_tags.body', []),
        ];
    }
}

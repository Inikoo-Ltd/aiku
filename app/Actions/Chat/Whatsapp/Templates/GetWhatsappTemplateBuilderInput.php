<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Models\Chat\MetaMessageTemplate;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Turns a stored template back into what the builder shows. Meta keeps the compiled form
 * — `{{1}}` and a separate sample list — while the builder works in named tags, so the
 * numbering has to be resolved back through the mapping that produced it.
 */
class GetWhatsappTemplateBuilderInput
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(MetaMessageTemplate $metaMessageTemplate): array
    {
        $data = $metaMessageTemplate->data ?? [];

        // A draft kept the builder's own input, which needs no reconstruction.
        if (filled(Arr::get($data, 'draft'))) {
            return Arr::get($data, 'draft');
        }

        $components = collect(Arr::get($data, 'components', []));
        $header     = $components->firstWhere('type', 'HEADER');
        $tags       = Arr::get($data, 'merge_tags', []);

        return [
            'category'      => $metaMessageTemplate->category,
            'header_format' => Arr::get($header ?? [], 'format', 'NONE'),
            'header_text'   => $this->restoreTags(Arr::get($header ?? [], 'text'), Arr::get($tags, 'header', [])),
            'body'          => $this->restoreTags(
                Arr::get($components->firstWhere('type', 'BODY') ?? [], 'text'),
                Arr::get($tags, 'body', [])
            ),
            'footer'        => Arr::get($components->firstWhere('type', 'FOOTER') ?? [], 'text'),
            'buttons'       => $this->restoreButtons(
                Arr::get($components->firstWhere('type', 'BUTTONS') ?? [], 'buttons', [])
            ),
        ];
    }

    /**
     * @param  array<int, string>  $tags
     */
    protected function restoreTags(?string $text, array $tags): ?string
    {
        foreach ($tags as $index => $tag) {
            $text = str_replace('{{'.($index + 1).'}}', '['.$tag.']', (string) $text);
        }

        return $text;
    }

    /**
     * A link carrying a variable is a dynamic link in the builder, which splits what Meta
     * keeps as one URL type so the difference is visible while writing.
     *
     * @param  array<int, array<string, mixed>>  $buttons
     * @return array<int, array<string, mixed>>
     */
    protected function restoreButtons(array $buttons): array
    {
        return collect($buttons)->map(function (array $button) {
            $url       = (string) Arr::get($button, 'url');
            $isDynamic = Arr::get($button, 'type') === 'URL' && str_contains($url, '{{1}}');

            return array_filter([
                'type'         => $isDynamic ? 'URL_DYNAMIC' : Arr::get($button, 'type'),
                'text'         => Arr::get($button, 'text'),
                'url'          => $url ?: null,
                'url_example'  => Arr::get($button, 'example.0'),
                'phone_number' => Arr::get($button, 'phone_number'),
            ], fn ($value) => $value !== null);
        })->values()->all();
    }
}

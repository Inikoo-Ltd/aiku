<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates\Concerns;

use App\Models\Chat\MetaMessageTemplate;
use Illuminate\Support\Arr;

trait WithWhatsappTemplateLocalData
{
    /**
     * Meta owns the template itself, but the merge-tag mapping, the internal label and
     * the rejection note only exist here. Overwriting `data` with the Graph payload
     * would drop them, forcing a re-mapping after every synchronization.
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    protected function mergeLocalData(array $template, ?MetaMessageTemplate $existing): array
    {
        if (!$existing) {
            return $template;
        }

        $local = $existing->data ?? [];

        foreach (['label', 'rejected_reason'] as $key) {
            if (filled(Arr::get($local, $key))) {
                $template[$key] = Arr::get($local, $key);
            }
        }

        $tags = Arr::get($local, 'merge_tags.body', []);

        if ($tags && count($tags) === $this->countBodyVariables($template)) {
            $template['merge_tags'] = ['body' => $tags];
        }

        return $template;
    }

    /**
     * A mapping only survives while it still lines up with the body placeholders, so an
     * edit made on Meta's side drops the stale mapping instead of shifting every value.
     *
     * @param  array<string, mixed>  $template
     */
    protected function countBodyVariables(array $template): int
    {
        $body = collect(Arr::get($template, 'components', []))->firstWhere('type', 'BODY');

        preg_match_all('/\{\{\s*\d+\s*\}\}/', (string) Arr::get($body ?? [], 'text', ''), $matches);

        return count(array_unique($matches[0]));
    }
}

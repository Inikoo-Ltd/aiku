<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebsite;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the named pages and shared parts are now, as checksums: a change is only applied to the content
 * it was previewed against, and only reverted while nothing changed it afterwards.
 */
class GetStaticWebsiteContentState
{
    use AsAction;

    /**
     * @param list<string> $paths
     * @param list<string> $codes
     *
     * @return array{pages: array<string, array{checksum: string, title: ?string, description: ?string, size: int}|null>, components: array<string, array{checksum: string, size: int}|null>}
     */
    public function handle(?StaticWebsite $staticWebsite, array $paths, array $codes): array
    {
        $pages      = $staticWebsite ? $staticWebsite->webpages()->whereIn('path', $paths)->get(['path', 'title', 'description', 'html'])->keyBy('path') : collect();
        $components = $staticWebsite ? $staticWebsite->components()->whereIn('code', $codes)->get(['code', 'html'])->keyBy('code') : collect();

        $state = ['pages' => [], 'components' => []];
        foreach ($paths as $path) {
            $page                  = $pages->get($path);
            $state['pages'][$path] = $page ? [
                'checksum'    => md5((string) $page->html),
                'title'       => $page->title,
                'description' => $page->description,
                'size'        => strlen((string) $page->html),
            ] : null;
        }
        foreach ($codes as $code) {
            $component                  = $components->get($code);
            $state['components'][$code] = $component ? ['checksum' => md5($component->html), 'size' => strlen($component->html)] : null;
        }

        return $state;
    }

    /**
     * @param array{pages: array, components: array} $state
     */
    public function describe(array $state): string
    {
        $pages = collect($state['pages'])->map(fn (?array $page, string $path) => $page
            ? "page $path (".($page['title'] ?: 'no title').', '.round($page['size'] / 1024).' KB)'
            : "page $path: not there");
        $components = collect($state['components'])->map(fn (?array $component, string $code) => $component
            ? "shared $code (".round($component['size'] / 1024).' KB)'
            : "shared $code: not there");

        return $pages->merge($components)->implode('; ');
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlogPosts
{
    /**
     * @return Collection<int, array{slug:string,title:string,summary:string,date:Carbon,tags:array<int,string>,body:string,html:string}>
     */
    public static function all(): Collection
    {
        return collect(glob(resource_path('markdown/aiku-public/blog/*.md')))
            ->map(fn (string $path) => self::parse($path))
            ->sortByDesc('date')
            ->values();
    }

    public static function find(string $slug): ?array
    {
        $path = resource_path("markdown/aiku-public/blog/{$slug}.md");

        return preg_match('/^[a-z0-9-]+$/', $slug) && is_file($path) ? self::parse($path) : null;
    }

    private static function parse(string $path): array
    {
        $raw = file_get_contents($path);
        preg_match('/^---\n(.*?)\n---\n(.*)$/s', $raw, $matches);
        $meta = collect(explode("\n", $matches[1]))
            ->mapWithKeys(function (string $line) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));

                return [$key => $value];
            });

        return [
            'slug' => basename($path, '.md'),
            'title' => $meta['title'],
            'summary' => $meta['summary'],
            'date' => Carbon::parse($meta['date']),
            'tags' => array_map('trim', explode(',', $meta['tags'] ?? '')),
            'body' => $matches[2],
            'html' => Str::markdown($matches[2]),
        ];
    }
}

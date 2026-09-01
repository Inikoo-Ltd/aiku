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
     * URL suffix => language shown in the switcher. Keys match the app's locale files.
     */
    public const array LANGUAGES = [
        'zh-hans' => [
            'name'   => '中文',
            'notice' => '本页为方便阅读而翻译。如有出入，以英文版为准。',
            'stale'  => '英文原文在本译文之后已有更新。请以英文版为准。',
        ],
        'id' => [
            'name'   => 'Bahasa Indonesia',
            'notice' => 'Diterjemahkan untuk kemudahan. Bila ada perbedaan, versi bahasa Inggris yang berlaku.',
            'stale'  => 'Versi bahasa Inggris telah berubah setelah terjemahan ini dibuat. Versi bahasa Inggris yang berlaku.',
        ],
        'es' => [
            'name'   => 'Español',
            'notice' => 'Traducido por comodidad. Si algo difiere, la versión en inglés es la que prevalece.',
            'stale'  => 'El original en inglés ha cambiado desde esta traducción. La versión en inglés es la que prevalece.',
        ],
        'sk' => [
            'name'   => 'Slovenčina',
            'notice' => 'Preložené pre pohodlie. V prípade rozdielov platí anglická verzia.',
            'stale'  => 'Anglický originál sa od tohto prekladu zmenil. Platí anglická verzia.',
        ],
    ];

    /**
     * @return Collection<int, array{slug:string,title:string,summary:string,date:Carbon,tags:array<int,string>,body:string,html:string}>
     */
    public static function all(string $dir = 'blog'): Collection
    {
        return self::everything($dir)
            ->where('lang', 'en')
            ->values();
    }

    /**
     * Every file including translations. Only the language switcher needs these.
     */
    public static function everything(string $dir = 'blog'): Collection
    {
        return collect(glob(resource_path("markdown/aiku-public/{$dir}/*.md")))
            ->map(fn (string $path) => self::parse($path))
            ->reject(fn (array $post) => $post['date']->isFuture())
            ->sortByDesc('date')
            ->values();
    }

    /**
     * The same article in every language it exists in, English first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function translations(array $doc, string $dir = 'blog'): Collection
    {
        return self::everything($dir)
            ->where('base_slug', $doc['base_slug'])
            ->sortBy(fn (array $other) => $other['lang'] === 'en' ? '' : $other['lang'])
            ->values();
    }

    public static function find(string $slug, string $dir = 'blog'): ?array
    {
        $path = resource_path("markdown/aiku-public/{$dir}/{$slug}.md");

        $post = preg_match('/^[a-z0-9-]+$/', $slug) && is_file($path) ? self::parse($path) : null;

        return $post && $post['date']->isFuture() ? null : $post;
    }

    public static function helpFor(?string $routeName): ?array
    {
        if (!$routeName) {
            return null;
        }

        $match = self::all('docs')
            ->flatMap(fn (array $doc) => collect($doc['help_routes'])->map(fn (string $prefix) => ['prefix' => $prefix, 'doc' => $doc]))
            ->filter(fn (array $candidate) => str_starts_with($routeName, $candidate['prefix']))
            ->sortByDesc(fn (array $candidate) => strlen($candidate['prefix']))
            ->first();

        return $match ? [
            'title' => $match['doc']['title'],
            'url' => 'https://'.config('app.domain').'/docs/'.$match['doc']['slug'],
        ] : null;
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

        $slug = basename($path, '.md');
        $lang = collect(array_keys(self::LANGUAGES))->first(fn (string $code) => str_ends_with($slug, '-'.$code)) ?? 'en';

        return [
            'slug' => $slug,
            'lang' => $lang,
            'base_slug' => $lang === 'en' ? $slug : Str::beforeLast($slug, '-'.$lang),
            'source_date' => isset($meta['source_date']) ? Carbon::parse($meta['source_date']) : null,
            'title' => $meta['title'],
            'summary' => $meta['summary'],
            'date' => Carbon::parse($meta['date']),
            'tags' => array_map('trim', explode(',', $meta['tags'] ?? '')),
            'help_routes' => array_values(array_filter(array_map('trim', explode(',', $meta['help_routes'] ?? '')))),
            'category' => $meta['category'] ?? null,
            'series' => $meta['series'] ?? null,
            'series_order' => (int) ($meta['order'] ?? 0),
            'body' => $matches[2],
            'reading_minutes' => max(1, (int) round(str_word_count(strip_tags($matches[2])) / 220)),
            'html' => Str::markdown($matches[2]),
        ];
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsController;

class ShowDoc
{
    use AsController;

    public function handle(string $slug): View
    {
        $doc = BlogPosts::find($slug, 'docs');
        abort_unless((bool) $doc, 404);

        $translations = BlogPosts::translations($doc, 'docs');
        $english = $translations->firstWhere('lang', 'en');

        return view('aiku-public.docs.show', [
            'doc' => $doc,
            'series' => $this->seriesDocs($doc),
            'more' => $this->relatedDocs($doc),
            'translations' => $translations->count() > 1 ? $translations : collect(),
            'english' => $english,
            'isStale' => $doc['lang'] !== 'en'
                && $english
                && (!$doc['source_date'] || $english['date']->gt($doc['source_date'])),
        ]);
    }

    /**
     * @param  array{slug:string,series:string|null}  $doc
     */
    private function seriesDocs(array $doc): Collection
    {
        if (!$doc['series']) {
            return collect();
        }

        return BlogPosts::everything('docs')
            ->where('series', $doc['series'])
            ->groupBy('base_slug')
            ->map(fn (Collection $versions) => $versions->firstWhere('lang', $doc['lang']) ?? $versions->firstWhere('lang', 'en'))
            ->filter()
            ->sortBy('series_order')
            ->values();
    }

    /**
     * @param  array{slug:string,tags:array<int,string>,series:string|null}  $doc
     */
    private function relatedDocs(array $doc): Collection
    {
        $everything = BlogPosts::everything('docs');

        return BlogPosts::all('docs')
            ->where('base_slug', '!=', $doc['base_slug'])
            ->sortByDesc(fn (array $other) => count(array_intersect($other['tags'], $doc['tags'])) * 1e12 + $other['date']->timestamp)
            ->take(3)
            ->map(fn (array $english) => $everything->firstWhere('slug', $english['slug'].'-'.$doc['lang']) ?? $english)
            ->values();
    }
}

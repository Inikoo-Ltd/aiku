<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsController;

class ShowDocs
{
    use AsController;

    public function handle(Request $request): View
    {
        $docs = BlogPosts::all('docs');
        $tags = $docs->flatMap(fn (array $doc) => $doc['tags'])->countBy()->sortKeys();
        $tag = $request->query('tag');
        $tag = $tags->has($tag) ? $tag : null;

        $category = $request->query('category');
        $category = $docs->contains(fn (array $doc) => $doc['category'] === $category) ? $category : null;

        $filtered = $docs
            ->when($tag, fn ($filteredDocs) => $filteredDocs->filter(fn (array $doc) => in_array($tag, $doc['tags'], true)))
            ->when($category, fn ($filteredDocs) => $filteredDocs->filter(fn (array $doc) => $doc['category'] === $category))
            ->values();

        return view('aiku-public.docs.index', [
            'docs' => $filtered,
            'tags' => $tags,
            'tag' => $tag,
            'category' => $category,
        ]);
    }
}

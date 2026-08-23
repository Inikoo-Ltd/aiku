<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsController;

class ShowBlog
{
    use AsController;

    public function handle(Request $request): View
    {
        $posts = BlogPosts::all();
        $tags = $posts->flatMap(fn (array $post) => $post['tags'])->countBy()->sortKeys();
        $tag = $request->query('tag');
        $tag = $tags->has($tag) ? $tag : null;

        $filtered = $tag ? $posts->filter(fn (array $post) => in_array($tag, $post['tags'], true))->values() : $posts;
        $perPage = 10;
        $page = max(1, (int) $request->query('page', 1));
        $lastPage = max(1, (int) ceil($filtered->count() / $perPage));
        $page = min($page, $lastPage);

        return view('aiku-public.blog.index', [
            'posts' => $filtered->forPage($page, $perPage)->values(),
            'tags' => $tags,
            'tag' => $tag,
            'page' => $page,
            'lastPage' => $lastPage,
        ]);
    }
}

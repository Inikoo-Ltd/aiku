<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\Concerns\AsController;

class ShowBlogPost
{
    use AsController;

    public const array RENAMED = [
        'an-mcp-server-for-a-whole-business' => 'rag-is-dead-give-the-model-the-tools',
    ];

    public function handle(string $slug): View|RedirectResponse
    {
        if (isset(self::RENAMED[$slug])) {
            return redirect()->route('aiku-public.blog.show', self::RENAMED[$slug], 301);
        }

        $post = BlogPosts::find($slug);
        abort_unless($post, 404);

        return view('aiku-public.blog.show', [
            'post' => $post,
            'more' => $this->relatedPosts($post),
        ]);
    }

    /**
     * @param  array{slug:string,tags:array<int,string>}  $post
     */
    private function relatedPosts(array $post): \Illuminate\Support\Collection
    {
        return BlogPosts::all()
            ->where('slug', '!=', $post['slug'])
            ->sortByDesc(fn (array $other) => count(array_intersect($other['tags'], $post['tags'])) * 1e12 + $other['date']->timestamp)
            ->take(3)
            ->values();
    }
}

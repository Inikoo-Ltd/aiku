<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Contracts\View\View;
use Lorisleiva\Actions\Concerns\AsController;

class ShowBlogPost
{
    use AsController;

    public function handle(string $slug): View
    {
        $post = BlogPosts::find($slug);
        abort_unless($post, 404);

        return view('aiku-public.blog.show', [
            'post' => $post,
            'more' => BlogPosts::all()->where('slug', '!=', $slug)->take(3),
        ]);
    }
}

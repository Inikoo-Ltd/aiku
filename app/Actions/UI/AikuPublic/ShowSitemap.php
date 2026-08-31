<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsController;

class ShowSitemap
{
    use AsController;

    public function handle(): Response
    {
        $urls = collect([
            ['loc' => route('aiku-public.home'), 'lastmod' => BlogPosts::all()->first()['date'] ?? now()],
            ['loc' => route('aiku-public.blog.index'), 'lastmod' => BlogPosts::all()->first()['date'] ?? now()],
        ])->concat(
            BlogPosts::all()->map(fn (array $post) => [
                'loc' => route('aiku-public.blog.show', $post['slug']),
                'lastmod' => $post['date'],
            ])
        );

        return response(view('aiku-public.sitemap', ['urls' => $urls]), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsController;

class ShowFeed
{
    use AsController;

    public function handle(): Response
    {
        return response(view('aiku-public.feed', ['posts' => BlogPosts::all()]), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

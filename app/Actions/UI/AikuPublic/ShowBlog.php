<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Contracts\View\View;
use Lorisleiva\Actions\Concerns\AsController;

class ShowBlog
{
    use AsController;

    public function handle(): View
    {
        return view('aiku-public.blog.index', [
            'posts' => BlogPosts::all(),
        ]);
    }
}

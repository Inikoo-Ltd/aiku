<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 00:59:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Images;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectImageShortUrl
{
    use AsAction;

    public function handle(string $code): ?string
    {
        return DB::table('image_short_urls')->where('code', Str::before($code, '.'))->value('url');
    }

    public function asController(string $code): RedirectResponse
    {
        $url = $this->handle($code);
        abort_unless($url, 404);

        return redirect()->away($url)->header('Cache-Control', 'public, max-age=86400');
    }
}

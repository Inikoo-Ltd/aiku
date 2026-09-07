<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsCommand;

class PingIndexNow
{
    use AsCommand;

    public string $commandSignature = 'aiku-public:indexnow';
    public string $commandDescription = 'Submit every public URL to IndexNow (Bing, Yandex, etc.)';

    public function handle(): int
    {
        $key = config('services.indexnow.key');
        if (! $key) {
            return 0;
        }

        $urls = BlogPosts::all()
            ->map(fn (array $post) => route('aiku-public.blog.show', $post['slug']))
            ->prepend(route('aiku-public.blog.index'))
            ->prepend(route('aiku-public.home'))
            ->values();

        Http::post('https://api.indexnow.org/indexnow', [
            'host' => parse_url(route('aiku-public.home'), PHP_URL_HOST),
            'key' => $key,
            'keyLocation' => route('aiku-public.indexnow-key'),
            'urlList' => $urls->all(),
        ])->throw();

        return $urls->count();
    }

    public function asCommand(Command $command): int
    {
        $count = $this->handle();
        $command->info($count ? "Submitted {$count} URLs to IndexNow." : 'INDEXNOW_KEY not set, skipped.');

        return 0;
    }
}

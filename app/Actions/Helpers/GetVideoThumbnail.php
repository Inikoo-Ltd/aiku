<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Jul 2026 23:21:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsObject;

class GetVideoThumbnail
{
    use AsObject;

    public function handle(?string $videoUrl): ?string
    {
        if (!$videoUrl) {
            return null;
        }

        $host = parse_url($videoUrl, PHP_URL_HOST) ?? '';
        $host = str_replace('www.', '', $host);

        if (str_contains($host, 'youtube.com') || $host === 'youtu.be') {
            $id = $this->youtubeId($videoUrl);

            return $id ? "https://img.youtube.com/vi/$id/hqdefault.jpg" : null;
        }

        if (str_contains($host, 'vimeo.com')) {
            return $this->vimeoThumbnail($videoUrl);
        }

        return null;
    }

    private function youtubeId(string $url): ?string
    {
        $host = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?? '');

        if ($host === 'youtu.be') {
            return ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/') ?: null;
        }

        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        return $query['v'] ?? (last(explode('/', parse_url($url, PHP_URL_PATH) ?? '')) ?: null);
    }

    private function vimeoThumbnail(string $url): ?string
    {
        $cacheKey = 'video_thumbnail:'.md5($url);
        $cached   = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $thumbnail = rescue(
        /**
         * @throws \Illuminate\Http\Client\ConnectionException
         */ function () use ($url) {
            $response = Http::timeout(5)->get('https://vimeo.com/api/oembed.json', ['url' => $url]);

            return $response->successful() ? $response->json('thumbnail_url') : null;
        },
            null,
            false
        );

        if ($thumbnail) {
            Cache::forever($cacheKey, $thumbnail);
        }

        return $thumbnail;
    }
}

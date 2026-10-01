<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 01:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Images;

use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Tuupola\Base32;

/**
 * Rewrites the imgproxy URLs of a storefront page (about 150 characters each, a third of a family
 * page's bytes) into links on the website's own domain, /i/{media}/{signature}/{options}.{ext},
 * served by ServeWebsiteShortImage. Nothing is stored: the link carries the media id, the
 * imgproxy options and an HMAC, and the image route rebuilds the imgproxy URL from them. An
 * original-format link takes its file's extension, unsigned, so Cloudflare caches it.
 */
class ShortenWebsiteImageUrls
{
    use AsAction;

    public function handle(array $webpageData, Website $website, string $baseUrl): array
    {
        if (!Arr::get($website->settings, 'short_image_urls') || !config('img-proxy.base_url')) {
            return $webpageData;
        }

        $baseUrl = parse_url($baseUrl, PHP_URL_SCHEME).'://'.parse_url($baseUrl, PHP_URL_HOST).(($port = parse_url($baseUrl, PHP_URL_PORT)) ? ':'.$port : '');
        $pattern = $this->pattern();

        array_walk_recursive($webpageData, function (&$value) use ($baseUrl, $pattern) {
            if (is_string($value) && str_contains($value, config('img-proxy.base_url'))) {
                $value = preg_replace_callback($pattern, fn ($match) => $this->shortUrl($match, $baseUrl) ?? $match[0], $value);
            }
        });

        return $webpageData;
    }

    public function pattern(): string
    {
        return '#'.preg_quote(rtrim(config('img-proxy.base_url'), '/'), '#')
            .'/[A-Za-z0-9_-]+/(?:(rs:[0-9a-z:]*)/)?([A-Za-z0-9_-]+)(?:\.([a-z0-9]{2,4}))?(?![A-Za-z0-9_./:-])#';
    }

    /**
     * @param  array<int, string>  $match  0 => long url, 1 => imgproxy options, 2 => base64 source, 3 => extension
     */
    public function shortUrl(array $match, string $baseUrl): ?string
    {
        $source  = base64_decode(strtr($match[2], '-_', '+/'));
        $mediaId = self::mediaIdFromSource($source);
        if (!$mediaId) {
            return null;
        }

        $options   = $match[1] ?? '';
        $extension = $match[3] ?? '';
        $id        = base_convert((string) $mediaId, 10, 36);
        $urlExtension = $extension !== '' ? $extension : strtolower(pathinfo($source, PATHINFO_EXTENSION));

        return $baseUrl.'/i/'.$id.'/'.self::signature($id, $options, $extension)
            .($options !== '' ? '/'.$options : '')
            .(preg_match('/^[a-z0-9]{2,4}$/', $urlExtension) ? '.'.$urlExtension : '');
    }

    /**
     * Only an original file sitting in its media folder, {prefix}{xx}/{yy}/{base32 id}/{file}, as
     * written by InverseBase32PathGenerator; anything else keeps its long url.
     */
    public static function mediaIdFromSource(string $source): ?int
    {
        if (!preg_match('#^(?:local://media/|s3://[^/]+/)(?:.+/)?([0-9A-Z]{2})/([0-9A-Z]{2})/([0-9A-Z]{4,})/[^/]+$#', $source, $parts)) {
            return null;
        }

        [, $last, $previous, $encodedId] = $parts;
        if (substr($encodedId, -2) !== $last || substr($encodedId, -4, 2) !== $previous) {
            return null;
        }

        $decoded = new Base32(['characters' => Base32::CROCKFORD, 'padding' => false, 'crockford' => true])->decode($encodedId);

        return ctype_digit($decoded) ? (int) $decoded : null;
    }

    public static function signature(string $id, string $options, string $extension): string
    {
        return substr(strtr(base64_encode(hash_hmac('sha256', "$id/$options.$extension", config('app.key'), true)), '+/', '-_'), 0, 8);
    }
}

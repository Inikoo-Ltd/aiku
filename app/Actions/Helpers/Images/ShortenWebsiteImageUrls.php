<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 01:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Images;

use App\Http\Resources\Traits\HasCardWebImages;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Tuupola\Base32;

/**
 * Rewrites the imgproxy URLs of a storefront page (about 150 characters each, a third of a family
 * page's bytes) into links on the website's own domain, /i/{media}/{signature}/{width}x{height}.{ext},
 * served by ServeWebsiteShortImage. Nothing is stored: the link carries the media id, the
 * imgproxy options and an HMAC, and the image route rebuilds the imgproxy URL from them. An
 * original-format link takes its file's extension, unsigned, so Cloudflare caches it.
 * An image inside a product or category (a node with a name, a code and web images) is named after it,
 * /i/{media}/{signature}/{width}x{height or o}/{name}.{ext}, the name left out of the signature
 * so a renamed product never breaks a link search engines have kept.
 */
class ShortenWebsiteImageUrls
{
    use AsAction;
    use HasCardWebImages;

    public function handle(array $webpageData, Website $website, string $baseUrl): array
    {
        if (!Arr::get($website->settings, 'short_image_urls') || !config('img-proxy.base_url')) {
            return $webpageData;
        }

        $baseUrl = parse_url($baseUrl, PHP_URL_SCHEME).'://'.parse_url($baseUrl, PHP_URL_HOST).(($port = parse_url($baseUrl, PHP_URL_PORT)) ? ':'.$port : '');

        return $this->shortenUrls($webpageData, $baseUrl, $this->pattern(), config('img-proxy.base_url'), null);
    }

    private function shortenUrls(array $data, string $baseUrl, string $pattern, string $imgProxyBaseUrl, ?string $name): array
    {
        if (is_string($data['name'] ?? null) && is_string($data['code'] ?? null) && array_key_exists('web_images', $data)) {
            $name = self::fileName($data['name']);
        }

        if (isset($data['_cig'], $data['v']) && is_array($data['v'])) {
            return $this->shortenCompactGallery($data, $baseUrl, $pattern, $name);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->shortenUrls($value, $baseUrl, $pattern, $imgProxyBaseUrl, $name);
            } elseif (is_string($value) && str_contains($value, $imgProxyBaseUrl)) {
                $data[$key] = preg_replace_callback($pattern, fn ($match) => $this->shortUrl($match, $baseUrl, $name) ?? $match[0], $value);
            }
        }

        return $data;
    }

    /**
     * A product card gallery ships its imgproxy URLs compacted (HasCardWebImages); each one is
     * rebuilt, shortened and the gallery compacted again around the shared name.
     */
    private function shortenCompactGallery(array $gallery, string $baseUrl, string $pattern, ?string $name): array
    {
        $urls = [];
        foreach ($gallery['v'] as $format => $variant) {
            $url = $gallery['_cig']['u'].'/'.Str::beforeLast($variant, '|').'/'.$gallery['_cig']['b'].Str::afterLast($variant, '|');

            $urls[$format] = preg_replace_callback($pattern, fn ($match) => $this->shortUrl($match, $baseUrl, $name) ?? $match[0], $url);
        }

        return $this->compactGallery($urls);
    }

    public static function fileName(string $name): ?string
    {
        return rtrim(substr(Str::slug(strip_tags($name)), 0, 60), '-') ?: null;
    }

    public function pattern(): string
    {
        return '#'.preg_quote(rtrim(config('img-proxy.base_url'), '/'), '#')
            .'/[A-Za-z0-9_-]+/(?:(rs:[0-9a-z:]*)/)?([A-Za-z0-9_-]+)(?:\.([a-z0-9]{2,4}))?(?![A-Za-z0-9_./:-])#';
    }

    /**
     * @param  array<int, string>  $match  0 => long url, 1 => imgproxy options, 2 => base64 source, 3 => extension
     */
    public function shortUrl(array $match, string $baseUrl, ?string $name = null): ?string
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

        if ($name && preg_match('/^[a-z0-9]{2,4}$/', $urlExtension)) {
            return $baseUrl.'/i/'.$id.'/'.self::signature($id, $options, $extension).'/'.($options !== '' ? self::compactOptions($options) : 'o').'/'.$name.'.'.$urlExtension;
        }

        return $baseUrl.'/i/'.$id.'/'.self::signature($id, $options, $extension)
            .($options !== '' ? '/'.self::compactOptions($options) : '')
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

    /**
     * rs::1440:1440:: is written 1440x1440; a resize with a type, enlarge or extend keeps imgproxy's form.
     */
    public static function compactOptions(string $options): string
    {
        return preg_match('/^rs::(\d*):(\d*)::$/', $options, $size) ? $size[1].'x'.$size[2] : $options;
    }

    public static function expandOptions(string $options): string
    {
        return preg_match('/^(\d*)x(\d*)$/', $options, $size) ? 'rs::'.$size[1].':'.$size[2].'::' : $options;
    }

    public static function signature(string $id, string $options, string $extension): string
    {
        return substr(strtr(base64_encode(hash_hmac('sha256', "$id/$options.$extension", config('app.key'), true)), '+/', '-_'), 0, 8);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Helpers\Images\ServeWebsiteShortImage;
use App\Helpers\ImgProxy\Image;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Gmail and Outlook show no AVIF, and classic Outlook no WebP. An image link copied from a
 * storefront or an aiku page (the browser hands over the <picture>'s AVIF source) is pasted into
 * emails, so before sending every AVIF/WebP imgproxy or /i/ short link is swapped for the
 * same image in its uploaded format (JPEG, PNG or GIF), at the same size. Signatures cover the
 * format, so each link is rebuilt and signed again; anything that is not ours stays as it is.
 */
class ConvertEmailImagesToOriginalFormat
{
    use AsAction;

    private const string SHORT_URL_PATTERN = '#https?://[A-Za-z0-9.-]+(?::\d+)?/i/([0-9a-z]+)/([A-Za-z0-9_-]{8})(?:/(o|rs:[0-9a-z:]*|[0-9]*x[0-9]*))?(?:/[a-z0-9]+(?:-[a-z0-9]+)*)?\.(avif|webp)(?![A-Za-z0-9_./-])#';

    public function handle(string $html): string
    {
        if (!config('img-proxy.base_url') || (!str_contains($html, '.avif') && !str_contains($html, '.webp'))) {
            return $html;
        }

        $html = preg_replace_callback(self::SHORT_URL_PATTERN, fn (array $match) => $this->remember($match[0], fn () => $this->longUrl($match)), $html);

        return preg_replace_callback($this->imgProxyPattern(), fn (array $match) => $this->remember($match[0], fn () => $this->originalFormatUrl($match)), $html);
    }

    private function imgProxyPattern(): string
    {
        return '#'.preg_quote(rtrim(config('img-proxy.base_url'), '/'), '#')
            .'/[A-Za-z0-9_-]+/(?:(rs:[0-9a-z:]*)/)?(?:q:(\d+)/)?([A-Za-z0-9_-]+)\.(?:avif|webp)(?![A-Za-z0-9_./:-])#';
    }

    /**
     * ponytail: one cache read per AVIF/WebP link per recipient; convert at publish time instead if big mailshots ever feel it.
     */
    private function remember(string $url, callable $rebuild): string
    {
        return Cache::remember('email-image-original-format:'.md5($url), now()->addDay(), fn () => $rebuild() ?? $url);
    }

    /**
     * @param  array<int, string>  $match  1 => media id, 2 => signature, 3 => options, 4 => extension
     */
    private function longUrl(array $match): ?string
    {
        $options = ($match[3] ?? '') === 'o' ? '' : ($match[3] ?? '');

        return ServeWebsiteShortImage::make()->handle($match[1], $match[2], $options.'.'.$match[4]);
    }

    /**
     * @param  array<int, string>  $match  1 => resize options, 2 => quality, 3 => base64 source
     */
    private function originalFormatUrl(array $match): string
    {
        $image = new Image()->make(base64_decode(strtr($match[3], '-_', '+/')));

        if ($match[1] !== '') {
            [, $type, $width, $height, $enlarge, $extend] = array_pad(explode(':', $match[1]), 6, '');
            $image->resize($width === '' ? null : (int) $width, $height === '' ? null : (int) $height, $type ?: null, $enlarge ?: null, $extend ?: null);
        }

        if ($match[2] !== '') {
            $image->quality((int) $match[2]);
        }

        return GetImgProxyUrl::run($image->extension(null));
    }
}

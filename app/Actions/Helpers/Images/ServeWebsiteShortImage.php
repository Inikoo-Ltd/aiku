<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 01:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Images;

use App\Helpers\ImgProxy\Image;
use App\Models\Helpers\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Answers the short image links written by ShortenWebsiteImageUrls with the image itself (not a
 * redirect, which would cost every image a round trip) and lets Cloudflare keep it for a year.
 * Where imgproxy runs beside the app, IMGPROXY_INTERNAL_URL fetches it there instead of out
 * through Cloudflare and back; an image that does not come in time is a short-lived 504.
 */
class ServeWebsiteShortImage
{
    use AsAction;

    public function handle(string $id, string $signature, string $optionsAndExtension): ?string
    {
        if ($optionsAndExtension === '' && Str::contains($signature, '.')) {
            $optionsAndExtension = '.'.Str::afterLast($signature, '.');
            $signature           = Str::beforeLast($signature, '.');
        }

        $extension = Str::contains($optionsAndExtension, '.') ? Str::afterLast($optionsAndExtension, '.') : '';
        $options   = ShortenWebsiteImageUrls::expandOptions($extension !== '' ? Str::beforeLast($optionsAndExtension, '.') : $optionsAndExtension);

        if (!hash_equals(ShortenWebsiteImageUrls::signature($id, $options, $extension), $signature)) {
            if (!hash_equals(ShortenWebsiteImageUrls::signature($id, $options, ''), $signature)) {
                return null;
            }
            $extension = '';
        }

        $media = Media::find((int) base_convert($id, 36, 10));
        if (!$media) {
            return null;
        }

        $image = new Image()->make($media->getImgProxyFilename(), $media->is_animated);
        if ($options !== '') {
            [, $type, $width, $height, $enlarge, $extend] = array_pad(explode(':', $options), 6, '');
            $image->resize($width === '' ? null : (int) $width, $height === '' ? null : (int) $height, $type ?: null, $enlarge ?: null, $extend ?: null);
        }

        return GetImgProxyUrl::run($image->extension($extension ?: null));
    }

    public function asController(string $id, string $signature, string $optionsAndExtension = ''): Response
    {
        $url = $this->handle($id, $signature, $optionsAndExtension);
        abort_unless($url, 404);

        if ($internalUrl = config('img-proxy.internal_url')) {
            $url = Str::replaceStart(config('img-proxy.base_url'), $internalUrl, $url);
        }

        try {
            $image = Http::timeout(20)->get($url);
        } catch (ConnectionException) {
            return response('', 504, ['Cache-Control' => 'public, max-age=60']);
        }

        if (!$image->successful()) {
            return response('', $image->status(), ['Cache-Control' => 'public, max-age=60']);
        }

        return response($image->body(), 200, [
            'Content-Type'  => $image->header('Content-Type'),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}

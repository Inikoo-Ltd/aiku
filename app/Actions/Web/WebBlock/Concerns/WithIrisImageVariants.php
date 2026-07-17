<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Concerns;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Models\Helpers\Media;
use Tuupola\Base32;

trait WithIrisImageVariants
{
    protected function findMediaFromImgProxyUrl(?string $url): ?Media
    {
        if (!$url) {
            return null;
        }

        $encodedPath = last(explode('/', parse_url($url, PHP_URL_PATH) ?? ''));
        $encodedPath = preg_replace('/\.[a-z0-9]+$/i', '', $encodedPath);

        $decodedPath = base64_decode(strtr($encodedPath, '-_', '+/').str_repeat('=', (4 - strlen($encodedPath) % 4) % 4), true);

        if (!$decodedPath || !preg_match('#media/[^/]+/[^/]+/([0-9A-Z]+)/#', $decodedPath, $matches)) {
            return null;
        }

        $base32 = new Base32([
            'characters' => Base32::CROCKFORD,
            'padding' => false,
            'crockford' => true,
        ]);

        $mediaId = $base32->decode($matches[1]);

        if (!is_numeric($mediaId)) {
            return null;
        }

        return Media::find((int) $mediaId);
    }

    /**
     * @param  array<int>  $widths
     * @return array<string, string>
     */
    protected function getWidthSrcSets(Media $media, array $widths): array
    {
        $formats = ['original'];
        if (in_array('avif', config('img-proxy.formats')) && !$media->is_animated) {
            $formats[] = 'avif';
        }
        if (in_array('webp', config('img-proxy.formats'))) {
            $formats[] = 'webp';
        }

        $srcSets = [];
        foreach ($formats as $format) {
            $entries = [];
            foreach ($widths as $width) {
                $image = $media->getImage()->resize($width, $width);
                if ($format !== 'original') {
                    $image = $image->extension($format);
                }
                $entries[] = GetImgProxyUrl::run($image).' '.$width.'w';
            }
            $srcSets[$format] = implode(', ', $entries);
        }

        return $srcSets;
    }
}

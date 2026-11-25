<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebsite;
use GuzzleHttp\Psr7\DroppingStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Stores a JPG, PNG or WebP image (a file the user gave ChatGPT, one uploaded through a signed
 * link, or one downloaded from elsewhere) in the info website's own files as a WebP no wider or taller than MAX_SIDE, so pages
 * can show it from /_assets/. The name carries a hash of the image, so an image is stored once and
 * a stored name never changes what it shows (assets are cached for a year).
 */
class StoreStaticWebsiteImage
{
    use AsAction;

    public const int MAX_BYTES = 20 * 1024 * 1024;

    public const int MAX_SIDE = 2400;

    public const int MAX_PIXELS = 60_000_000;

    private const array IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * @return array{src: string, name: string, width: int, height: int, kilobytes: int, already_stored: bool}
     */
    public function handle(StaticWebsite $staticWebsite, string $bytes, ?string $fileName = null): array
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            $this->refuse('The image is bigger than 20 MB.');
        }
        $size = @getimagesizefromstring($bytes);
        if (!$size || !in_array($size[2], self::IMAGE_TYPES, true)) {
            $this->refuse('Only JPG, PNG and WebP images can be added.');
        }
        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            $this->refuse('The image is too large: at most 60 megapixels.');
        }

        $stem = Str::limit(Str::slug(pathinfo((string) $fileName, PATHINFO_FILENAME)), 40, '') ?: 'image';
        $name = $stem.'-'.substr(hash('sha256', $bytes), 0, 12).'.webp';

        $disk        = StaticWebsite::assetDisk();
        $storagePath = $staticWebsite->assetStoragePath($name);
        $existing    = $disk->exists($storagePath);

        if (!$existing) {
            $original  = tempnam(sys_get_temp_dir(), 'static-image-');
            $optimised = $original.'.webp';
            try {
                file_put_contents($original, $bytes);
                try {
                    Image::load($original)->fit(Fit::Max, self::MAX_SIDE, self::MAX_SIDE)->quality(85)->format('webp')->save($optimised);
                } catch (\Throwable) {
                    $this->refuse('The image could not be read; animated images are not supported.');
                }
                $disk->put($storagePath, (string) file_get_contents($optimised), ['ContentType' => StaticWebsite::assetContentType($name)]);
            } finally {
                @unlink($original);
                @unlink($optimised);
            }
        }

        $stored = (string) $disk->get($storagePath);
        $final  = getimagesizefromstring($stored);

        return [
            'src'            => StaticWebsite::ASSET_URL_PREFIX.$name,
            'name'           => $name,
            'width'          => $final[0],
            'height'         => $final[1],
            'kilobytes'      => (int) ceil(strlen($stored) / 1024),
            'already_stored' => $existing,
        ];
    }

    public function download(string $url): string
    {
        $parts = parse_url($url);
        $host  = $parts['host'] ?? '';
        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            $this->refuse('The image must come from an https address.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === [] || array_filter($addresses, fn (string $address) => !filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE))) {
            $this->refuse("The image address $host is not a public internet address.");
        }

        try {
            $response = Http::timeout(30)
                ->withOptions([
                    'allow_redirects' => false,
                    'sink'            => new DroppingStream(Utils::streamFor(fopen('php://temp', 'w+')), self::MAX_BYTES + 1),
                    'curl'            => [CURLOPT_RESOLVE => [$host.':'.($parts['port'] ?? 443).':'.$addresses[0]]],
                    'on_headers'      => function (ResponseInterface $response) {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new RuntimeException('too large');
                        }
                    },
                ])
                ->get($url);
        } catch (\Throwable) {
            $this->refuse('The image could not be downloaded, or it is bigger than 20 MB.');
        }

        if (!$response->successful() || strlen($response->body()) > self::MAX_BYTES) {
            $this->refuse('The image could not be downloaded (HTTP '.$response->status().'), or it is bigger than 20 MB.');
        }

        return $response->body();
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['image' => $message]);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Web block videos and their posters live on the public R2 bucket under web-videos/, keyed by
 * checksum, so the visitor's browser streams them from the Cloudflare edge (range requests work)
 * and the same file uploaded twice is stored once.
 */
class StoreWebBlockVideoFile
{
    use AsObject;

    public const string DISK = 'catalogue-iris-r2';

    public const array CONTENT_TYPES = [
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'jpg'  => 'image/jpeg',
        'webp' => 'image/webp',
        'png'  => 'image/png',
    ];

    public function handle(string $path, string $extension): string
    {
        $key  = 'web-videos/'.md5_file($path).'.'.$extension;
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($key)) {
            $stream  = fopen($path, 'r');
            $written = $disk->writeStream($key, $stream, [
                'ContentType'  => self::CONTENT_TYPES[$extension] ?? 'application/octet-stream',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (!$written) {
                throw ValidationException::withMessages(['video' => __('The video could not be stored. Please try again.')]);
            }
        }

        return self::publicUrl($key);
    }

    public static function publicUrl(string $key): string
    {
        $domain = preg_replace('#^https?://#', '', rtrim((string) config('filesystems.disks.'.self::DISK.'.url'), '/'));

        return 'https://'.$domain.'/'.$key;
    }
}

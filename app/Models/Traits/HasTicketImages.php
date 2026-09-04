<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Helpers\Media;
use Illuminate\Http\UploadedFile;

trait HasTicketImages
{
    /**
     * @param array<int, UploadedFile> $images
     */
    public function attachTicketImages(array $images): void
    {
        foreach ($images as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }
            StoreMediaFromFile::run($this, [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
                'checksum'     => md5_file($image->getPathName()),
            ], 'ticket_images');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ticketImageSources(): array
    {
        return $this->getMedia('ticket_images')
            ->map(fn (Media $media) => GetPictureSources::run($media->getImage()->resize(0, 0)))
            ->all();
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Helpers\TicketComment;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Helpers\Media;
use Illuminate\Http\UploadedFile;

trait HasTicketImages
{
    /**
     * @return array<int, string>
     */
    public static function ticketFileRules(): array
    {
        return [
            'file',
            'extensions:jpg,jpeg,png,bmp,gif,svg,webp,pdf,docx,xls,xlsx,csv',
            'mimes:jpg,jpeg,png,bmp,gif,svg,webp,pdf,docx,xls,xlsx,csv,txt,zip',
            'max:10240',
        ];
    }

    /**
     * @param array<int, UploadedFile> $images
     */
    public function attachTicketImages(array $images): void
    {
        foreach ($images as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }
            $this->attachTicketFile($image->getPathName(), $image->getClientOriginalName(), $image->getMimeType());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attachTicketFile(string $path, string $originalName, ?string $mimeType = null, array $properties = []): Media
    {
        $isImage    = str_starts_with((string) ($mimeType ?: mime_content_type($path)), 'image/');
        $collection = $isImage ? 'ticket_images' : 'ticket_attachments';

        $media = StoreMediaFromFile::run($this, [
            'path'         => $path,
            'originalName' => $originalName,
            'extension'    => pathinfo($originalName, PATHINFO_EXTENSION) ?: null,
            'checksum'     => md5_file($path),
        ], $collection, $isImage ? 'image' : 'file');

        if ($properties) {
            $media->setCustomProperty('source', $properties)->save();
        }

        return $media;
    }

    public function ticketAttachments(?string $routeName = null): array
    {
        $routeName       ??= request()->routeIs('retina.*') ? 'retina.dropshipping.tickets.attachments.show' : 'grp.tickets.attachments.show';
        $ticketReference = $this instanceof TicketComment ? $this->ticket->reference : $this->reference;

        return $this->getMedia('ticket_attachments')
            ->map(fn (Media $media) => [
                'name' => $media->name,
                'url'  => route($routeName, ['ticket' => $ticketReference, 'media' => $media->ulid]),
                'size' => $media->size,
                'mime' => $media->mime_type,
            ])
            ->all();
    }

    public function ticketImageSources(): array
    {
        return $this->getMedia('ticket_images')
            ->map(fn (Media $media) => [...GetPictureSources::run($media->getImage()->resize(0, 0)), 'name' => $media->name])
            ->all();
    }
}

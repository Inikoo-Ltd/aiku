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
use Closure;
use Illuminate\Http\UploadedFile;

trait HasTicketImages
{
    public const array TICKET_VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov'];

    /**
     * @return array<int, string|Closure>
     */
    public static function ticketFileRules(): array
    {
        return [
            'file',
            'extensions:jpg,jpeg,png,bmp,gif,svg,webp,pdf,docx,xls,xlsx,csv,zip,rar,7z,mp4,webm,mov',
            'mimes:jpg,jpeg,png,bmp,gif,svg,webp,pdf,docx,xls,xlsx,csv,txt,zip,rar,7z,mp4,webm,mov',
            'max:51200',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (!$value instanceof UploadedFile) {
                    return;
                }

                $isVideo = in_array(strtolower($value->getClientOriginalExtension()), self::TICKET_VIDEO_EXTENSIONS, true);

                if (!$isVideo && $value->getSize() > 10 * 1024 * 1024) {
                    $fail(__(':attribute is too big. Videos can be up to 50 MB, other files up to 10 MB.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ticketFileValidationMessages(): array
    {
        return [
            'images.max'          => __('You can attach up to 5 files at a time.'),
            'images.*.file'       => __(':attribute could not be uploaded. Please try again.'),
            'images.*.uploaded'   => __(':attribute could not be uploaded. Please try again.'),
            'images.*.extensions' => __(':attribute cannot be attached. You can attach pictures, PDF, Word, Excel, CSV, ZIP, RAR and 7z files, and MP4, WebM or MOV videos.'),
            'images.*.mimes'      => __(':attribute cannot be attached. You can attach pictures, PDF, Word, Excel, CSV, ZIP, RAR and 7z files, and MP4, WebM or MOV videos.'),
            'images.*.max'        => __(':attribute is too big. Videos can be up to 50 MB, other files up to 10 MB.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ticketFileValidationAttributes(mixed $images): array
    {
        return collect(is_array($images) ? $images : [])
            ->mapWithKeys(fn (mixed $image, int|string $index) => [
                "images.$index" => $image instanceof UploadedFile ? '"'.$image->getClientOriginalName().'"' : __('File :number', ['number' => (int) $index + 1]),
            ])
            ->all();
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

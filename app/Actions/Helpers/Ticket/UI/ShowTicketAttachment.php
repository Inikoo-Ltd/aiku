<?php

/*
 * Author Louis Perez
 * Created on 14-09-2026-15h-01m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Models\Helpers\Media;
use App\Models\Helpers\Ticket;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ShowTicketAttachment extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    private const int MAX_ZIP_ENTRIES = 1000;

    public function handle(Media $media, bool $withZipContents = false): Response
    {
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        if ($withZipContents && $this->isZip($media->name)) {
            $contents = $this->zipContents($disk, $path, config("filesystems.disks.{$media->disk}.driver") === 'local');

            return $contents === null ? response()->json(['message' => __('This zip file cannot be read')], 422) : response()->json($contents);
        }

        if (config("filesystems.disks.{$media->disk}.driver") !== 'local') {
            return $disk->response($path, $media->name, ['Content-Type' => $media->mime_type], $this->dispositionFor($media->name));
        }

        return response()->file($disk->path($path), ['Content-Type' => $media->mime_type])
            ->setContentDisposition($this->dispositionFor($media->name), $media->name, str_replace('%', '', Str::ascii($media->name)) ?: 'attachment');
    }

    public function asController(Ticket $ticket, Media $media, ActionRequest $request): Response
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        abort_unless($ticket->hasAttachmentVisibleTo($media, $request->user()), 404);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($media, $request->boolean('contents'));
    }

    private function dispositionFor(string $fileName): string
    {
        return $this->isZip($fileName) ? 'attachment' : 'inline';
    }

    private function isZip(string $fileName): bool
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'zip';
    }

    /**
     * @return array{total: int, entries: array<int, array{name: string, size: int, is_directory: bool}>}|null
     */
    private function zipContents(Filesystem $disk, string $path, bool $isLocalDisk): ?array
    {
        $temporaryPath = null;

        if ($isLocalDisk) {
            $zipPath = $disk->path($path);
        } else {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'ticket_zip_');
            $source        = $disk->readStream($path);
            $target        = fopen($temporaryPath, 'wb');
            stream_copy_to_stream($source, $target);
            fclose($target);
            if (is_resource($source)) {
                fclose($source);
            }
            $zipPath = $temporaryPath;
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
                return null;
            }

            $entries = [];
            for ($entryIndex = 0; $entryIndex < min($zip->numFiles, self::MAX_ZIP_ENTRIES); $entryIndex++) {
                $stat = $zip->statIndex($entryIndex);
                if ($stat === false) {
                    continue;
                }
                $entries[] = [
                    'name'         => $stat['name'],
                    'size'         => (int) $stat['size'],
                    'is_directory' => str_ends_with($stat['name'], '/'),
                ];
            }

            $total = $zip->numFiles;
            $zip->close();

            return ['total' => $total, 'entries' => $entries];
        } finally {
            if ($temporaryPath !== null) {
                @unlink($temporaryPath);
            }
        }
    }
}

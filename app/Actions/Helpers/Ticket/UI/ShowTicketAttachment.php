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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ShowTicketAttachment extends OrgAction
{
    private const int MAX_ARCHIVE_ENTRIES = 1000;

    private const array ARCHIVE_EXTENSIONS = ['zip', 'rar', '7z'];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Media $media, bool $withArchiveContents = false): Response
    {
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        if ($withArchiveContents && $this->isArchive($media->name)) {
            return $this->archiveContentsResponse($disk, $path, config("filesystems.disks.{$media->disk}.driver") === 'local', $this->extensionOf($media->name));
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
        abort_unless($ticket->canPreviewAttachmentsBy($request->user()), 403);
        abort_unless($ticket->hasAttachmentVisibleTo($media, $request->user()), 404);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($media, $request->boolean('contents'));
    }

    private function dispositionFor(string $fileName): string
    {
        return $this->isArchive($fileName) ? 'attachment' : 'inline';
    }

    private function extensionOf(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    private function isArchive(string $fileName): bool
    {
        return in_array($this->extensionOf($fileName), self::ARCHIVE_EXTENSIONS, true);
    }

    private function archiveContentsResponse(Filesystem $disk, string $path, bool $isLocalDisk, string $extension): JsonResponse
    {
        if ($extension !== 'zip' && !Process::run('bsdtar --version')->successful()) {
            return response()->json(['message' => __('This server cannot read :type files yet. Ask an administrator to install libarchive-tools.', ['type' => strtoupper($extension)])], 422);
        }

        $temporaryPath = null;

        if ($isLocalDisk) {
            $archivePath = $disk->path($path);
        } else {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'ticket_archive_');
            $source        = $disk->readStream($path);
            $target        = fopen($temporaryPath, 'wb');
            stream_copy_to_stream($source, $target);
            fclose($target);
            if (is_resource($source)) {
                fclose($source);
            }
            $archivePath = $temporaryPath;
        }

        try {
            $contents = $extension === 'zip' ? $this->zipContents($archivePath) : $this->bsdtarContents($archivePath);

            return $contents === null
                ? response()->json(['message' => __('This archive file cannot be read')], 422)
                : response()->json($contents);
        } finally {
            if ($temporaryPath !== null) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @return array{total: int, entries: array<int, array{name: string, size: int, is_directory: bool}>}|null
     */
    private function zipContents(string $archivePath): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            return null;
        }

        $entries = [];
        for ($entryIndex = 0; $entryIndex < min($zip->numFiles, self::MAX_ARCHIVE_ENTRIES); $entryIndex++) {
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
    }

    /**
     * @return array{total: int, entries: array<int, array{name: string, size: int, is_directory: bool}>}|null
     */
    private function bsdtarContents(string $archivePath): ?array
    {
        $result = Process::timeout(20)->run('bsdtar -tvf '.escapeshellarg($archivePath));
        if ($result->failed()) {
            return null;
        }

        $lines = array_values(array_filter(explode("\n", trim($result->output())), fn (string $line) => trim($line) !== ''));

        $entries = collect($lines)
            ->take(self::MAX_ARCHIVE_ENTRIES)
            ->map(function (string $line) {
                if (!preg_match('/^(\S)\S*\s+\d+\s+\S+\s+\S+\s+(\d+)\s+\S+\s+\S+\s+\S+\s+(.+)$/', rtrim($line), $matches)) {
                    return null;
                }
                $isDirectory = $matches[1] === 'd' || str_ends_with($matches[3], '/');

                return [
                    'name'         => $isDirectory ? rtrim($matches[3], '/').'/' : $matches[3],
                    'size'         => $isDirectory ? 0 : (int) $matches[2],
                    'is_directory' => $isDirectory,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return ['total' => count($lines), 'entries' => $entries];
    }
}

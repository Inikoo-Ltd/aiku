<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 01 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairScriptWebBlocksBase64Files
{
    use AsAction;
    use WithAttachMediaToModel;

    private const string BASE64_DATA_URI_PATTERN = '~data:(image/[a-z0-9.+-]+|application/pdf);base64,([A-Za-z0-9+/=\s]++)(?=["\')&])~i';

    private const array IMAGE_EXTENSIONS_BY_MIME_TYPE = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function handle(WebBlock $webBlock, bool $apply = true, ?Command $command = null, ?Webpage $webpageToLeaveUnpublished = null): int
    {
        $code = data_get($webBlock->layout, 'data.fieldValue.value');

        if (!is_string($code) || !str_contains($code, ';base64,')) {
            return 0;
        }

        $files = $this->findFiles($code, $webBlock->webpages->isNotEmpty());

        if ($files === null) {
            $command?->error("Web block: $webBlock->id || Could not be scanned: ".preg_last_error_msg());

            return 0;
        }

        $this->logWebBlock($webBlock, $code, $files, $command);

        if (!$apply || !$files) {
            return count($files);
        }

        $urlsByChecksum = [];
        $replacements   = [];

        foreach ($files as $dataUri => $file) {
            $checksum = md5($file['content']);

            $urlsByChecksum[$checksum] ??= $this->uploadFile($webBlock, $file, $checksum, count($urlsByChecksum) + 1);

            $replacements[$dataUri] = $urlsByChecksum[$checksum];
        }

        $this->replaceCode($webBlock, strtr($code, $replacements));
        $this->publishWebpages($webBlock, $command, $webpageToLeaveUnpublished);

        return count($replacements);
    }

    /**
     * Uploads one base64 file of the script and links every copy of it to the upload.
     *
     * @return string|null the uploaded file URL, or null when the script has no such file that can be uploaded
     */
    public function repairFile(WebBlock $webBlock, string $dataUri, ?Webpage $webpageToLeaveUnpublished = null): ?string
    {
        $code = data_get($webBlock->layout, 'data.fieldValue.value');

        if (!is_string($code) || !str_contains($code, $dataUri)) {
            return null;
        }

        $file = $this->findFiles($code, $webBlock->webpages->isNotEmpty())[$dataUri] ?? null;

        if (!$file) {
            return null;
        }

        $url = $this->uploadFile($webBlock, $file, md5($file['content']), $webBlock->images()->count() + 1);

        $this->replaceCode($webBlock, strtr($code, [$dataUri => $url]));
        $this->publishWebpages($webBlock, null, $webpageToLeaveUnpublished);

        return $url;
    }

    /**
     * @param array{content: string, extension: string, name: string|null} $file
     */
    private function uploadFile(WebBlock $webBlock, array $file, string $checksum, int $position): string
    {
        return $file['extension'] === 'pdf'
            ? $this->uploadPdf($webBlock, $file, $checksum)
            : $this->uploadImage($webBlock, $file, $checksum, $position);
    }

    private function replaceCode(WebBlock $webBlock, string $code): void
    {
        $layout = $webBlock->layout;
        data_set($layout, 'data.fieldValue.value', $code);
        $webBlock->update(['layout' => $layout]);
    }

    /**
     * @return array<string, array{content: string, extension: string, name: string|null}>|null keyed by data URI
     */
    private function findFiles(string $code, bool $canStorePdfs): ?array
    {
        if (preg_match_all(self::BASE64_DATA_URI_PATTERN, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return null;
        }

        $files = [];

        foreach ($matches as [[$dataUri, $offset], [$mimeType], [$base64]]) {
            $content   = base64_decode(preg_replace('/\s+/', '', $base64), true);
            $extension = $content ? $this->detectExtension(strtolower($mimeType), $content) : null;

            if (!$extension || ($extension === 'pdf' && !$canStorePdfs)) {
                continue;
            }

            $files[$dataUri] = [
                'content'   => $content,
                'extension' => $extension,
                'name'      => $this->getDownloadName($code, $offset, strlen($dataUri)),
            ];
        }

        return $files;
    }

    private function detectExtension(string $mimeType, string $content): ?string
    {
        if ($mimeType === 'application/pdf') {
            return str_starts_with($content, '%PDF-') ? 'pdf' : null;
        }

        $imageSize = @getimagesizefromstring($content);

        return $imageSize ? Arr::get(self::IMAGE_EXTENSIONS_BY_MIME_TYPE, $imageSize['mime']) : null;
    }

    private function getDownloadName(string $code, int $offset, int $length): ?string
    {
        $tagStart = strrpos($code, '<', $offset - strlen($code));
        $tagEnd   = strpos($code, '>', $offset + $length);

        if ($tagStart === false || $tagEnd === false || strpos($code, '>', $tagStart) < $offset) {
            return null;
        }

        $tag = substr($code, $tagStart, $offset - $tagStart).substr($code, $offset + $length, $tagEnd - $offset - $length);

        return preg_match('~\bdownload\s*=\s*["\']([^"\']+)["\']~i', $tag, $download) ? trim($download[1]) : null;
    }

    /**
     * @param array<string, array{content: string, extension: string, name: string|null}> $files
     */
    private function logWebBlock(WebBlock $webBlock, string $code, array $files, ?Command $command): void
    {
        $fileTypes = collect($files)->countBy('extension')->map(fn (int $count, string $extension) => "$extension x$count")->implode(', ');

        preg_match_all('~data:([a-z0-9.+/-]+);base64,~i', strtr($code, array_fill_keys(array_keys($files), '')), $untouchedDataUris);
        $untouchedTypes = collect($untouchedDataUris[1])->countBy()->map(fn (int $count, string $type) => "$type x$count")->implode(', ');

        $command?->line("Web block: $webBlock->id || Base64 files: ".($fileTypes ?: 'none')." || Left untouched: ".($untouchedTypes ?: 'none'));
        foreach ($webBlock->webpages as $webpage) {
            $command?->line("  Shop: {$webpage->shop->slug} || Website: {$webpage->website->domain} || Webpage: $webpage->code || ".$webpage->getUrl(true));
        }
    }

    /**
     * @param array{content: string, extension: string, name: string|null} $file
     */
    private function uploadImage(WebBlock $webBlock, array $file, string $checksum, int $position): string
    {
        $media = $this->withTemporaryFile($file, fn (string $path) => StoreMediaFromFile::run($webBlock, [
            'path'         => $path,
            'originalName' => $file['name'] ?? "web-block-$webBlock->id-image-$position.{$file['extension']}",
            'extension'    => $file['extension'],
            'checksum'     => $checksum,
        ], 'image'));

        $this->attachMediaToModel($webBlock, $media, 'image');

        return GetImgProxyUrl::run($media->getImage());
    }

    /**
     * @param array{content: string, extension: string, name: string|null} $file
     */
    private function uploadPdf(WebBlock $webBlock, array $file, string $checksum): string
    {
        $name = $file['name'] ?? "web-block-$webBlock->id-".substr($checksum, 0, 8).'.pdf';

        $media = $this->withTemporaryFile($file, function (string $path) use ($webBlock, $name) {
            $media = null;
            foreach ($webBlock->webpages as $webpage) {
                $media = SaveModelAttachment::run($webpage, [
                    'path'         => $path,
                    'originalName' => $name,
                    'extension'    => 'pdf',
                    'scope'        => 'webpage',
                    'caption'      => pathinfo($name, PATHINFO_FILENAME),
                ]);
            }

            return $media;
        });

        return "/attachment/$media->ulid";
    }

    /**
     * @param array{content: string, extension: string, name: string|null} $file
     */
    private function withTemporaryFile(array $file, callable $callback): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'web-block-file-').".{$file['extension']}";
        file_put_contents($path, $file['content']);

        try {
            return $callback($path);
        } finally {
            @unlink($path);
        }
    }

    private function publishWebpages(WebBlock $webBlock, ?Command $command, ?Webpage $webpageToLeaveUnpublished): void
    {
        foreach ($webBlock->webpages as $webpage) {
            $hasUnpublishedChanges = $webpage->is_dirty;

            UpdateWebpageContent::run($webpage);

            if ($webpage->is($webpageToLeaveUnpublished)) {
                continue;
            }

            if ($webpage->state === WebpageStateEnum::LIVE && !$hasUnpublishedChanges) {
                PublishWebpage::make()->action($webpage, [
                    'comment' => 'base64 files in script moved to uploaded files',
                ]);
            } elseif ($webpage->state === WebpageStateEnum::LIVE) {
                $command?->warn("  Webpage $webpage->code has unpublished changes, publish it to take the uploaded files live");
            }
        }
    }

    public string $commandSignature = 'repair:script_web_blocks_base64_files {website?} {--apply-changes}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();

        $website = $command->argument('website') ? Website::where('slug', $command->argument('website'))->firstOrFail() : null;
        $apply   = (bool)$command->option('apply-changes');
        $total   = 0;

        WebBlock::query()
            ->whereHas('webBlockType', fn ($query) => $query->where('code', 'script'))
            ->whereRaw("layout::text ilike '%;base64,%'")
            ->when($website, fn ($query) => $query->whereHas('webpages', fn ($webpages) => $webpages->where('webpages.website_id', $website->id)))
            ->select('id')
            ->chunkById(10, function (Collection $webBlockIds) use ($command, $apply, &$total) {
                foreach ($webBlockIds as $webBlockId) {
                    $total += $this->handle(WebBlock::find($webBlockId->id), $apply, $command);
                }
            });

        $command->info($apply ? "Uploaded $total base64 files." : "Found $total base64 files. Run with --apply-changes to upload them.");
    }
}

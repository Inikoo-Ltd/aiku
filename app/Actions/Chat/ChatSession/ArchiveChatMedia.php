<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Traits\WithArchiveOperations;
use App\Models\Chat\ChatMessage;
use App\Models\Helpers\Media;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves chat images and attachments older than the retention window off the application disk
 * into the archive database. The media row stays and is flagged archived; GetChatMediaContents
 * reads the bytes back from there. Each file is copied, checked against its md5 and only then
 * deleted, so a crashed run leaves at worst a file that exists in both places.
 */
class ArchiveChatMedia
{
    use AsAction;
    use WithArchiveOperations;

    public const ARCHIVE_TABLE = 'chat_media_files';

    public const COLLECTIONS = ['chat_images', 'chat_attachments'];

    public string $commandSignature = 'chat:archive_media {--c|chunk=100} {--l|limit=} {--d|dry-run}';

    public string $commandDescription = 'Move chat images and attachments older than the retention window into the archive database';

    public string $archiveConnection = 'archive';

    public function handle(int $chunkSize = 100, ?int $limit = null, bool $dryRun = false, ?Command $command = null): int
    {
        $query = Media::query()
            ->whereIn('collection_name', self::COLLECTIONS)
            ->where('model_type', ChatMessage::class)
            ->where('created_at', '<', now()->subDays(config('archive.chat_media_retention_days')))
            ->whereNull('custom_properties->archived_at')
            ->orderBy('id');

        if ($dryRun) {
            $count = $query->count();
            $command?->info("$count chat files would be archived");

            return $count;
        }

        $this->assertArchiveIsNotProduction();
        $this->ensureArchiveFilesTable();

        $archived = 0;

        while ($limit === null || $archived < $limit) {
            $batch = (clone $query)->limit($limit === null ? $chunkSize : min($chunkSize, $limit - $archived))->get();

            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $media) {
                $this->archive($media);
                $archived++;
            }

            $this->waitForReplication($command);
        }

        $command?->info("$archived chat files archived");

        return $archived;
    }

    private function archive(Media $media): void
    {
        $path = $media->getPath();

        if (!is_file($path)) {
            throw new Exception("Chat media $media->id has no file at $path, not archiving it");
        }

        $contents = file_get_contents($path);
        $checksum = md5($contents);

        $archive = DB::connection($this->archiveConnection);
        $archive->table(self::ARCHIVE_TABLE)->where('media_id', $media->id)->delete();
        $archive->insert(
            'insert into '.self::ARCHIVE_TABLE.' (media_id, contents, checksum, archived_at) values (?, decode(?, \'base64\'), ?, now())',
            [$media->id, base64_encode($contents), $checksum]
        );

        $storedChecksum = $archive->table(self::ARCHIVE_TABLE)->where('media_id', $media->id)->selectRaw('md5(contents) as checksum')->value('checksum');
        if ($storedChecksum !== $checksum) {
            throw new Exception("Archive copy verification failed for chat media $media->id");
        }

        $media->setCustomProperty('archived_at', now()->toIso8601String());
        $media->saveQuietly();

        unlink($path);
    }

    private function ensureArchiveFilesTable(): void
    {
        DB::connection($this->archiveConnection)->statement(
            'create table if not exists '.self::ARCHIVE_TABLE.' (media_id bigint primary key, contents bytea not null, checksum varchar(32) not null, archived_at timestamptz not null)'
        );
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $this->handle(
            (int) $command->option('chunk'),
            $command->option('limit') ? (int) $command->option('limit') : null,
            (bool) $command->option('dry-run'),
            $command
        );

        return 0;
    }
}

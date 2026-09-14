<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithJiraApi;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Media;
use App\Models\Helpers\Ticket;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ImportJiraTicketAttachments
{
    use AsAction;
    use WithJiraApi;

    public string $commandSignature = 'tickets:import_jira_attachments {ticket? : Ticket reference, e.g. HELP-3121}';
    public string $commandDescription = 'Copy the Jira attachments that are not yet in the ticket media, so the Jira HELP space can be archived';

    public const int KEEP_OVERSIZED_FILES_IN_JIRA_AFTER_RESOLVED_DAYS = 14;

    public function handle(Ticket $ticket, ?Command $command = null): int
    {
        $jiraAttachments = $this->jira()
            ->get('rest/api/3/issue/'.$ticket->data['jira_key'], ['fields' => 'attachment'])
            ->throw()
            ->json('fields.attachment', []);

        $alreadyImportedIds = $this->alreadyImportedJiraAttachmentIds($ticket);
        $mediaSizeLimit     = config('media-library.max_file_size');
        $imported           = 0;

        foreach ($jiraAttachments as $jiraAttachment) {
            if (in_array((string) $jiraAttachment['id'], $alreadyImportedIds, true)) {
                continue;
            }

            $size = (int) ($jiraAttachment['size'] ?? 0);

            if ($size > $mediaSizeLimit && $this->isResolvedLongAgo($ticket)) {
                $command?->warn("$ticket->reference: left in Jira {$jiraAttachment['filename']} (".round($size / 1024 / 1024).' MB)');

                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'jira');

            try {
                $this->jira()->sink($path)->get($jiraAttachment['content'])->throw();
                config(['media-library.max_file_size' => max($mediaSizeLimit, $size, filesize($path))]);
                $ticket->attachTicketFile($path, $jiraAttachment['filename'], $jiraAttachment['mimeType'] ?? null, ['jira_attachment_id' => (string) $jiraAttachment['id']]);
            } finally {
                config(['media-library.max_file_size' => $mediaSizeLimit]);
                @unlink($path);
            }

            $alreadyImportedIds[] = (string) $jiraAttachment['id'];
            $imported++;
        }

        return $imported;
    }

    private function isResolvedLongAgo(Ticket $ticket): bool
    {
        return $ticket->status === TicketStatusEnum::RESOLVED
            && $ticket->resolved_at?->lt(now()->subDays(self::KEEP_OVERSIZED_FILES_IN_JIRA_AFTER_RESOLVED_DAYS));
    }

    /**
     * @return array<int, string>
     */
    private function alreadyImportedJiraAttachmentIds(Ticket $ticket): array
    {
        return Media::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('model_type', 'Ticket')->where('model_id', $ticket->id))
                ->orWhere(fn ($query) => $query->where('model_type', 'TicketComment')->whereIn('model_id', $ticket->comments()->select('id'))))
            ->get(['custom_properties'])
            ->map(fn (Media $media) => $media->getCustomProperty('source.jira_attachment_id'))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $imported = 0;
        $failed   = 0;

        Ticket::withTrashed()
            ->whereNotNull('data->jira_key')
            ->when($command->argument('ticket'), fn ($query, $reference) => $query->where('reference', $reference))
            ->chunkById(100, function ($tickets) use ($command, &$imported, &$failed) {
                foreach ($tickets as $ticket) {
                    try {
                        $imported += $this->handle($ticket, $command);
                    } catch (Throwable $e) {
                        $failed++;
                        $command->error("$ticket->reference: ".$e->getMessage());
                    }
                }
            });

        $command->info("$imported attachments imported, $failed tickets failed");

        return $failed ? 1 : 0;
    }
}

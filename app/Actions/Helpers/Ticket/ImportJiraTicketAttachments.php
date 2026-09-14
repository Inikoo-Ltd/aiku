<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithJiraApi;
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

    public function handle(Ticket $ticket): int
    {
        $jiraAttachments = $this->jira()
            ->get('rest/api/3/issue/'.$ticket->data['jira_key'], ['fields' => 'attachment'])
            ->throw()
            ->json('fields.attachment', []);

        $alreadyImportedIds = $this->alreadyImportedJiraAttachmentIds($ticket);
        $imported           = 0;

        foreach ($jiraAttachments as $jiraAttachment) {
            if (in_array((string) $jiraAttachment['id'], $alreadyImportedIds, true)) {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'jira');

            try {
                $this->jira()->sink($path)->get($jiraAttachment['content'])->throw();
                $ticket->attachTicketFile($path, $jiraAttachment['filename'], $jiraAttachment['mimeType'] ?? null, ['jira_attachment_id' => (string) $jiraAttachment['id']]);
            } finally {
                @unlink($path);
            }

            $alreadyImportedIds[] = (string) $jiraAttachment['id'];
            $imported++;
        }

        return $imported;
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
                        $imported += $this->handle($ticket);
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

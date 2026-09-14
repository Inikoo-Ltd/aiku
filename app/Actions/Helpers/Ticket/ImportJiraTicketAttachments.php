<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Models\Helpers\Ticket;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ImportJiraTicketAttachments
{
    use AsAction;

    public string $commandSignature = 'tickets:import_jira_attachments {ticket? : Ticket reference, e.g. HELP-3121}';
    public string $commandDescription = 'Copy the attachments of tickets imported from Jira into the ticket media, so the Jira HELP space can be archived';

    public function handle(Ticket $ticket): int
    {
        $jiraAttachments = $this->jira()
            ->get('rest/api/3/issue/'.$ticket->data['jira_key'], ['fields' => 'attachment'])
            ->throw()
            ->json('fields.attachment', []);

        $imported = 0;

        foreach ($jiraAttachments as $jiraAttachment) {
            $importedIds = Arr::get($ticket->data, 'jira_imported_attachment_ids', []);

            if (in_array($jiraAttachment['id'], $importedIds)) {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'jira');

            try {
                $this->jira()->sink($path)->get($jiraAttachment['content'])->throw();
                $ticket->attachTicketFile($path, $jiraAttachment['filename'], $jiraAttachment['mimeType'] ?? null, ['jira_attachment_id' => $jiraAttachment['id']]);
            } finally {
                @unlink($path);
            }

            $ticket->data = [...$ticket->data, 'jira_imported_attachment_ids' => [...$importedIds, $jiraAttachment['id']]];
            $ticket->save();
            $imported++;
        }

        return $imported;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        if (!config('services.jira.email') || !config('services.jira.api_token')) {
            $command->error('Set JIRA_EMAIL and JIRA_API_TOKEN in .env');

            return 1;
        }

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

    private function jira(): PendingRequest
    {
        return Http::baseUrl(config('services.jira.base_url'))
            ->withBasicAuth(config('services.jira.email'), config('services.jira.api_token'))
            ->timeout(120)
            ->retry(3, 5000, throw: false);
    }
}

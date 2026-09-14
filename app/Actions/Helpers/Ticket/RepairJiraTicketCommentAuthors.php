<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairJiraTicketCommentAuthors
{
    use AsAction;

    public string $commandSignature = 'tickets:repair_jira_comment_authors {ticket? : Ticket reference, e.g. HELP-3079}';
    public string $commandDescription = 'Set the author of Jira imported ticket comments that have none, from the Jira comment author';

    public function handle(Ticket $ticket): int
    {
        $commentsWithoutAuthor = $ticket->comments()->whereNull('author_id')->get();

        if ($commentsWithoutAuthor->isEmpty()) {
            return 0;
        }

        $jira = Http::baseUrl(config('services.jira.base_url'))
            ->withBasicAuth(config('services.jira.email'), config('services.jira.api_token'))
            ->timeout(120)
            ->retry(3, 5000, throw: false);

        $issue = $jira->get('rest/api/3/issue/'.$ticket->data['jira_key'], ['fields' => 'reporter,comment'])->throw()->json('fields');

        $jiraAuthorsByTime = collect(data_get($issue, 'comment.comments', []))
            ->groupBy(fn (array $jiraComment) => str_replace('T', ' ', substr($jiraComment['created'], 0, 19)))
            ->map(fn ($jiraComments) => $jiraComments->pluck('author')->unique('accountId'));

        $repaired = 0;

        foreach ($commentsWithoutAuthor as $comment) {
            $jiraAuthors = $jiraAuthorsByTime->get($comment->created_at->utc()->format('Y-m-d H:i:s'));

            if ($jiraAuthors === null || $jiraAuthors->count() !== 1) {
                continue;
            }

            $author = $this->aikuAuthor($ticket, $jiraAuthors->first(), data_get($issue, 'reporter.accountId'));

            if ($author === null) {
                continue;
            }

            $comment->update(['author_type' => $author['type'], 'author_id' => $author['id']]);
            $repaired++;
        }

        return $repaired;
    }

    /**
     * @param array<string, mixed> $jiraAuthor
     * @return array{type: string, id: int}|null
     */
    private function aikuAuthor(Ticket $ticket, array $jiraAuthor, ?string $jiraReporterAccountId): ?array
    {
        if ($jiraAuthor['accountId'] === $jiraReporterAccountId && $ticket->reporter_id) {
            return ['type' => $ticket->reporter_type, 'id' => $ticket->reporter_id];
        }

        $email = strtolower((string) ($jiraAuthor['emailAddress'] ?? ''));

        if ($email === '') {
            return null;
        }

        $userIds = User::where(DB::raw('lower(email)'), $email)->pluck('id');

        return $userIds->count() === 1 ? ['type' => 'User', 'id' => $userIds->first()] : null;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        if (!config('services.jira.email') || !config('services.jira.api_token')) {
            $command->error('Set JIRA_EMAIL and JIRA_API_TOKEN in .env');

            return 1;
        }

        $repaired = 0;
        $failed   = 0;

        Ticket::withTrashed()
            ->whereNotNull('data->jira_key')
            ->whereHas('comments', fn ($query) => $query->whereNull('author_id'))
            ->when($command->argument('ticket'), fn ($query, $reference) => $query->where('reference', $reference))
            ->chunkById(100, function ($tickets) use ($command, &$repaired, &$failed) {
                foreach ($tickets as $ticket) {
                    try {
                        $repaired += $this->handle($ticket);
                    } catch (Throwable $e) {
                        $failed++;
                        $command->error("$ticket->reference: ".$e->getMessage());
                    }
                }
            });

        $command->info("$repaired comment authors repaired, $failed tickets failed, ".TicketComment::whereNull('author_id')->count().' comments still without author');

        return $failed ? 1 : 0;
    }
}

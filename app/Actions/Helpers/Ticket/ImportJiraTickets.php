<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Jira\Traits\WithJiraApiRequest;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class ImportJiraTickets
{
    use AsAction;
    use WithJiraApiRequest;

    public string $commandSignature = 'jira:import_tickets {project : HELP} {--base-url=} {--email=} {--token=} {--jql=} {--since= : only issues updated in the last N minutes}';

    private const array USERNAME_BY_JIRA_NAME = [
        'Raul A Perusquia' => 'raul',
        'Louis Perez'      => 'louis',
        'Artha'            => 'artha',
        'Oggie Sutrisna'   => 'oggie',
        'Vika Aqordi'      => 'vika',
        'Arya Permana'     => 'arya',
        'arya'             => 'arya',
    ];

    public function handle(Group $group, string $project, ?string $jql = null): int
    {
        if ($project !== 'HELP') {
            throw new \RuntimeException('Only HELP tickets are imported from Jira');
        }
        $type      = TicketTypeEnum::HELP;
        $jql       = $jql ?: 'project = '.$project.' ORDER BY created ASC';
        $imported  = 0;
        $pageToken = null;

        do {
            $page = $this->makeJiraRequest('POST', 'search/jql', array_filter([
                'jql'           => $jql,
                'maxResults'    => 100,
                'fields'        => ['summary', 'description', 'status', 'issuetype', 'priority', 'assignee', 'reporter', 'created', 'updated', 'resolutiondate', 'comment', 'attachment', 'customfield_10051', 'customfield_10294'],
                'nextPageToken' => $pageToken,
            ]));

            if (Arr::get($page, 'error')) {
                throw new \RuntimeException('Jira: '.implode(', ', Arr::get($page, 'messages', [])));
            }

            foreach (Arr::get($page, 'issues', []) as $issue) {
                $this->importIssue($group, $type, $issue);
                $imported++;
            }

            $pageToken = Arr::get($page, 'nextPageToken');
        } while ($pageToken);

        $this->advanceSequence($type);

        return $imported;
    }

    private function importIssue(Group $group, TicketTypeEnum $type, array $issue): Ticket
    {
        $fields   = $issue['fields'];
        $shop     = $this->resolveShop($group, $fields);
        $reporter = $this->resolveUser(Arr::get($fields, 'reporter'));
        $status   = $this->mapStatus(Arr::get($fields, 'status.name'));

        return Ticket::withoutTimestamps(fn () => TicketComment::withoutTimestamps(fn () => $this->upsertIssue($group, $type, $issue, $shop, $reporter, $status)));
    }

    private function upsertIssue(Group $group, TicketTypeEnum $type, array $issue, ?Shop $shop, ?User $reporter, TicketStatusEnum $status): Ticket
    {
        $fields = $issue['fields'];
        $ticket = Ticket::withTrashed()->updateOrCreate(
            ['reference' => $issue['key']],
            [
                'group_id'        => $group->id,
                'organisation_id' => $shop?->organisation_id,
                'shop_id'         => $shop?->id,
                'customer_id'     => null,
                'type'            => $type,
                'kind'            => $type === TicketTypeEnum::HELP ? $this->mapKind(Arr::get($fields, 'issuetype.name')) : null,
                'number'          => (int) Str::after($issue['key'], '-'),
                'status'          => $status,
                'priority'        => $this->mapPriority(Arr::get($fields, 'priority.name')),
                'subject'         => Str::limit((string) Arr::get($fields, 'summary'), 250),
                'description'     => $this->adfToText(Arr::get($fields, 'description')),
                'reporter_type'   => $reporter ? class_basename($reporter) : null,
                'reporter_id'     => $reporter?->id,
                'assignee_id'     => $this->resolveUser(Arr::get($fields, 'assignee'))?->id,
                'data'            => [
                    'jira_key'         => $issue['key'],
                    'jira_id'          => $issue['id'],
                    'jira_status'      => Arr::get($fields, 'status.name'),
                    'jira_issue_type'  => Arr::get($fields, 'issuetype.name'),
                    'jira_reporter'    => Arr::get($fields, 'reporter.displayName'),
                    'jira_assignee'    => Arr::get($fields, 'assignee.displayName'),
                    'customer_name'    => Arr::get($fields, 'customfield_10294'),
                    'reference_url'    => Arr::get($fields, 'customfield_10051'),
                    'attachments'      => collect(Arr::get($fields, 'attachment', []))->map(fn ($attachment) => Arr::only($attachment, ['filename', 'content', 'mimeType', 'size']))->all(),
                ],
                'resolved_at'     => $status === TicketStatusEnum::RESOLVED || $status === TicketStatusEnum::CLOSED ? $this->date(Arr::get($fields, 'resolutiondate')) ?? $this->date(Arr::get($fields, 'updated')) : null,
                'closed_at'       => $status === TicketStatusEnum::CLOSED ? $this->date(Arr::get($fields, 'resolutiondate')) ?? $this->date(Arr::get($fields, 'updated')) : null,
                'created_at'      => $this->date(Arr::get($fields, 'created')),
                'updated_at'      => $this->date(Arr::get($fields, 'updated')),
            ]
        );

        $this->importAttachments($ticket, Arr::get($fields, 'attachment', []));

        $ticket->comments()->delete();
        foreach (Arr::get($fields, 'comment.comments', []) as $comment) {
            $author = $this->resolveUser(Arr::get($comment, 'author'));
            $ticket->comments()->create([
                'author_type' => $author ? class_basename($author) : null,
                'author_id'   => $author?->id,
                'body'        => $this->adfToText(Arr::get($comment, 'body')) ?? '',
                'is_internal' => Arr::get($comment, 'jsdPublic') === false,
                'created_at'  => $this->date(Arr::get($comment, 'created')),
                'updated_at'  => $this->date(Arr::get($comment, 'updated')),
            ]);
        }

        return $ticket;
    }

    private function importAttachments(Ticket $ticket, array $attachments): void
    {
        $known = $ticket->media()->get()->map(fn ($media) => data_get($media->custom_properties, 'source.jira_attachment_id'))->filter()->all();

        foreach ($attachments as $attachment) {
            if (in_array($attachment['id'], $known) || !Arr::get($attachment, 'content')) {
                continue;
            }
            if (Arr::get($attachment, 'size', 0) > config('media-library.max_file_size')) {
                Log::warning('Jira attachment too big', ['ticket' => $ticket->reference, 'attachment' => $attachment['id'], 'size' => $attachment['size']]);
                continue;
            }
            $path = tempnam(sys_get_temp_dir(), 'jira');
            try {
                $response = $this->jiraClient()->connectTimeout(10)->timeout(120)->sink($path)->get($attachment['content']);
                if ($response->successful() && filesize($path) > 0) {
                    $ticket->attachTicketFile($path, $attachment['filename'], Arr::get($attachment, 'mimeType'), ['jira_attachment_id' => $attachment['id']]);
                } else {
                    Log::warning('Jira attachment skipped', ['ticket' => $ticket->reference, 'attachment' => $attachment['id'], 'status' => $response->status()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Jira attachment download failed', ['ticket' => $ticket->reference, 'attachment' => $attachment['id'], 'error' => $e->getMessage()]);
            } finally {
                @unlink($path);
            }
        }
    }

    private function resolveShop(Group $group, array $fields): ?Shop
    {
        $url = (string) Arr::get($fields, 'customfield_10051');
        if (preg_match('#/shops/([a-z0-9-]+)/#', $url, $matches)) {
            return Shop::where('group_id', $group->id)->where('slug', $matches[1])->first();
        }
        $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));

        return $host ? Website::where('group_id', $group->id)->where('domain', $host)->first()?->shop : null;
    }

    private function resolveUser(?array $jiraUser): ?User
    {
        if (!$jiraUser) {
            return null;
        }
        if ($email = Arr::get($jiraUser, 'emailAddress')) {
            if ($user = User::whereRaw('lower(email) = ?', [strtolower($email)])->first()) {
                return $user;
            }
        }
        $name = Arr::get($jiraUser, 'displayName');
        if (!$name) {
            return null;
        }
        if ($username = self::USERNAME_BY_JIRA_NAME[$name] ?? null) {
            return User::where('username', $username)->first();
        }

        return User::where('contact_name', $name)->first();
    }

    private function mapStatus(?string $status): TicketStatusEnum
    {
        return match (strtolower((string) $status)) {
            'in progress', 'escalated'                                                   => TicketStatusEnum::IN_PROGRESS,
            'waiting for customer', 'customer replied', 'reopen by customer', 'no reply' => TicketStatusEnum::WAITING,
            'done', 'resolved'                                                           => TicketStatusEnum::RESOLVED,
            'canceled', 'cancelled', 'no applicable', 'closed'                            => TicketStatusEnum::CLOSED,
            default                                                                      => TicketStatusEnum::OPEN,
        };
    }

    private function mapPriority(?string $priority): ChatPriorityEnum
    {
        return match (strtolower((string) $priority)) {
            'lowest', 'low' => ChatPriorityEnum::LOW,
            'high'          => ChatPriorityEnum::HIGH,
            'highest'       => ChatPriorityEnum::URGENT,
            default         => ChatPriorityEnum::NORMAL,
        };
    }

    private function mapKind(?string $issueType): ?TicketKindEnum
    {
        $issueType = strtolower((string) $issueType);

        return match (true) {
            str_contains($issueType, 'bug')                                                                => TicketKindEnum::BUG,
            str_contains($issueType, 'feature') || str_contains($issueType, 'improve') || str_contains($issueType, 'suggest') => TicketKindEnum::FEATURE,
            default                                                                                        => null,
        };
    }

    private function adfToText(mixed $node): ?string
    {
        if (is_string($node) || $node === null) {
            return $node;
        }
        $text = Arr::get($node, 'text', '');
        foreach (Arr::get($node, 'content', []) as $child) {
            $text .= $this->adfToText($child);
        }
        if (in_array(Arr::get($node, 'type'), ['paragraph', 'heading', 'listItem', 'codeBlock', 'blockquote', 'hardBreak'], true)) {
            $text .= "\n";
        }

        return trim($text) === '' ? null : $text;
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    private function advanceSequence(TicketTypeEnum $type): void
    {
        $max = Ticket::withTrashed()->where('type', $type)->max('number') ?? 0;
        DB::statement('SELECT setval(?, ?)', [$type->sequence(), max($max, 1)]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $group = Group::firstOrFail();
        app()->instance('group', $group);

        $this->setJiraGroup($group);
        if ($command->option('base-url')) {
            $this->setJiraCredentials([
                'base_url'  => $command->option('base-url'),
                'email'     => $command->option('email'),
                'api_token' => $command->option('token'),
            ]);
        } elseif (!$this->hasJiraCredentials()) {
            $holder = User::whereRaw("settings->'jira'->>'api_token' is not null")->first();
            abort_unless($holder, 1, 'No Jira credentials: pass --base-url --email --token');
            $this->setJiraCredentials($holder->settings['jira']);
        }

        $jql = $command->option('jql');
        if ($since = $command->option('since')) {
            $jql = 'project = '.strtoupper($command->argument('project')).' AND updated >= -'.((int) $since).'m ORDER BY updated ASC';
        }
        $imported = $this->handle($group, strtoupper($command->argument('project')), $jql);
        $command->info("Imported $imported tickets from Jira ".$command->argument('project'));

        return 0;
    }
}

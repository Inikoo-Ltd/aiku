<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Actions\Helpers\Ticket\Concerns\WithTicketsWriteGuard;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveSlackTicketReaction
{
    use AsAction;
    use WithSlack;

    public function handle(Group $group, string $channel, string $ts, ?string $reactedBy = null): ?Ticket
    {
        if ($existing = Ticket::where('data->slack->ts', $ts)->where('data->slack->channel_id', $channel)->first()) {
            return $existing;
        }

        $client  = $this->slackClient();
        $message = $client?->get('conversations.history', ['channel' => $channel, 'latest' => $ts, 'oldest' => $ts, 'inclusive' => true, 'limit' => 1])->json('messages.0');
        if (!$message) {
            Log::warning('Slack ticket reaction: message not found', ['channel' => $channel, 'ts' => $ts]);

            return null;
        }

        $text     = trim((string) Arr::get($message, 'text', ''));
        $reporter = $this->slackUserToAikuUser(Arr::get($message, 'user')) ?? $this->slackUserToAikuUser($reactedBy);
        [$subject, $description] = array_pad(explode("\n", $text, 2), 2, null);

        $ticket = StoreTicket::make()->action($group, [
            'type'          => TicketTypeEnum::HELP->value,
            'kind'          => TicketKindEnum::BUG->value,
            'subject'       => Str::limit($subject ?: 'Slack message', 255, ''),
            'description'   => trim((string) $description) ?: null,
            'reporter_type' => $reporter ? 'User' : null,
            'reporter_id'   => $reporter?->id,
            'data'          => [
                'slack' => [
                    'user_id'    => Arr::get($message, 'user'),
                    'channel_id' => $channel,
                    'ts'         => $ts,
                ],
            ],
        ]);

        foreach (Arr::get($message, 'files', []) as $file) {
            $this->attachSlackFile($ticket, $file);
        }

        PostTicketSlackThreadReply::run($ticket, $ticket->reference.' raised: '.route('grp.tickets.show', $ticket->reference));

        return $ticket;
    }

    private function attachSlackFile(Ticket $ticket, array $file): void
    {
        $url = Arr::get($file, 'url_private_download');
        if (!$url || Arr::get($file, 'size', 0) > config('media-library.max_file_size')) {
            return;
        }
        $path = tempnam(sys_get_temp_dir(), 'slack');
        try {
            $response = $this->slackClient()->timeout(60)->sink($path)->get($url);
            if ($response->successful()) {
                $ticket->attachTicketFile($path, Arr::get($file, 'name', 'file'), Arr::get($file, 'mimetype'), ['slack_file_id' => Arr::get($file, 'id')]);
            }
        } catch (\Throwable $e) {
            Log::warning('Slack ticket file download failed', ['ticket' => $ticket->reference, 'file' => Arr::get($file, 'id'), 'error' => $e->getMessage()]);
        } finally {
            @unlink($path);
        }
    }

    public function asController(Request $request): JsonResponse
    {
        abort_unless($this->slackSignatureIsValid($request), 401);

        if ($request->input('type') === 'url_verification') {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        $event = $request->input('event', []);
        if (
            Arr::get($event, 'type') !== 'reaction_added'
            || Arr::get($event, 'reaction') !== config('services.slack.ticket_reaction')
            || Arr::get($event, 'item.type') !== 'message'
            || WithTicketsWriteGuard::ticketsAreReadOnly()
        ) {
            return response()->json(['ok' => true]);
        }

        $group = Group::firstOrFail();
        dispatch(fn () => $this->handle($group, Arr::get($event, 'item.channel'), Arr::get($event, 'item.ts'), Arr::get($event, 'user')));

        return response()->json(['ok' => true]);
    }
}

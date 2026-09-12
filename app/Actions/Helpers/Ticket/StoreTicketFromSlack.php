<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTicketFromSlack
{
    use AsAction;
    use WithSlack;

    /**
     * @param  array{user_id?: string|null, channel_id?: string|null, ts?: string|null, subject?: string|null, description?: string|null, reference_url?: string|null, files?: array<int, array<string, mixed>>}  $slackData
     */
    public function handle(Group $group, array $slackData): Ticket
    {
        $reporter = $this->slackUserToAikuUser(Arr::get($slackData, 'user_id'));

        $ticket = StoreTicket::make()->action($group, array_filter([
            'type'          => TicketTypeEnum::HELP->value,
            'kind'          => TicketKindEnum::BUG->value,
            'subject'       => Str::limit(trim((string) Arr::get($slackData, 'subject')) ?: 'Slack message', 255, ''),
            'description'   => trim((string) Arr::get($slackData, 'description')) ?: null,
            'reference_url' => Arr::get($slackData, 'reference_url'),
            'reporter_type' => $reporter ? 'User' : null,
            'reporter_id'   => $reporter?->id,
            'data'          => [
                'slack' => Arr::only($slackData, ['user_id', 'channel_id', 'ts']),
            ],
        ], fn ($value) => $value !== null));

        foreach (Arr::get($slackData, 'files', []) as $file) {
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
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveSlackTicketCommand
{
    use AsAction;

    public function handle(Group $group, array $payload): Ticket
    {
        $text     = trim((string) ($payload['text'] ?? ''));
        $reporter = $this->slackUserToAikuUser($payload['user_id'] ?? null);
        [$subject, $description] = array_pad(explode("\n", $text, 2), 2, null);

        return StoreTicket::make()->action($group, [
            'type'          => TicketTypeEnum::HELP->value,
            'kind'          => TicketKindEnum::BUG->value,
            'subject'       => $subject,
            'description'   => trim((string) $description) ?: null,
            'reporter_type' => $reporter ? 'User' : null,
            'reporter_id'   => $reporter?->id,
            'data'          => [
                'slack' => [
                    'user_id'   => $payload['user_id'] ?? null,
                    'user_name' => $payload['user_name'] ?? null,
                    'channel'   => $payload['channel_name'] ?? null,
                ],
            ],
        ]);
    }

    private function slackUserToAikuUser(?string $slackUserId): ?User
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');
        if (!$slackUserId || !$token) {
            return null;
        }

        $email = Http::withToken($token)->get('https://slack.com/api/users.info', ['user' => $slackUserId])->json('user.profile.email');

        return $email ? User::whereRaw('lower(email) = ?', [strtolower($email)])->first() : null;
    }

    public function asController(Request $request): JsonResponse
    {
        abort_unless($this->signatureIsValid($request), 401);

        if (trim((string) $request->input('text')) === '') {
            return response()->json(['response_type' => 'ephemeral', 'text' => 'Usage: /ticket what is broken. Add a second line for details.']);
        }

        $ticket = $this->handle(Group::firstOrFail(), $request->all());

        return response()->json([
            'response_type' => 'ephemeral',
            'text'          => $ticket->reference.' raised: '.$ticket->subject."\n".route('grp.tickets.show', $ticket->reference),
        ]);
    }

    private function signatureIsValid(Request $request): bool
    {
        $secret    = config('services.slack.signing_secret');
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        if (!$secret || !$timestamp || abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $expected = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Slack-Signature'));
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent\Presence;

use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Models\Chat\ChatAgent;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class TrackChatAgentPresence
{
    use AsAction;

    public function handle(ChatAgent $agent, ChatAgentPresenceStatusEnum $status): ChatAgent
    {
        if ($status === ChatAgentPresenceStatusEnum::OFFLINE) {
            $agent->setOnline(false);
            $agent->update(['is_available' => false]);

            return $agent;
        }

        $wasOffline = !$agent->isConnected();

        $attributes = [
            'is_online'         => true,
            'presence_status'   => $status,
            'last_heartbeat_at' => now(),
        ];

        if ($status === ChatAgentPresenceStatusEnum::ONLINE) {
            $attributes['last_activity_at'] = now();
        }

        if ($wasOffline) {
            $attributes['is_available'] = $agent->isAvailable();
        }

        $agent->timestamps = false;
        $agent->forceFill($attributes)->saveQuietly();
        $agent->timestamps = true;

        return $agent;
    }


    public function asController(ActionRequest $request): JsonResponse
    {
        $agent = $request->user()?->chatAgent;

        if (!$agent) {
            return response()->json(['presence' => null, 'timings' => $this->timings()]);
        }

        $agent = $this->handle(
            $agent,
            ChatAgentPresenceStatusEnum::from($request->validated('status'))
        );

        return response()->json([
            'presence' => [
                'status'            => $agent->presenceStatus()->value,
                'is_available'      => (bool) $agent->is_available,
                'last_heartbeat_at' => $agent->last_heartbeat_at,
            ],
            'timings' => $this->timings(),
        ]);
    }


    /**
     * Handed back on every ping so the browser follows config/chat.php instead of keeping its
     * own copy of the thresholds, which would drift the moment either side is tuned.
     *
     * @return array{heartbeat_seconds: int, away_after_seconds: int, abandon_after_seconds: int}
     */
    protected function timings(): array
    {
        return [
            'heartbeat_seconds'     => (int) config('chat.presence.heartbeat_seconds'),
            'away_after_seconds'    => (int) config('chat.presence.away_after_seconds'),
            'abandon_after_seconds' => (int) config('chat.presence.abandon_after_seconds'),
        ];
    }


    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', ChatAgentPresenceStatusEnum::values())],
        ];
    }
}

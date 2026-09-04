<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaChatActivity
{
    use AsAction;

    public function handle(MetaChatSession $metaChatSession): array
    {
        try {
            $events = $metaChatSession->events()
                ->whereIn('event_type', [
                    ChatEventTypeEnum::OPEN,
                    ChatEventTypeEnum::TRANSFER_TO_AGENT,
                    ChatEventTypeEnum::PRIORITY,
                    ChatEventTypeEnum::CLOSE,
                    ChatEventTypeEnum::REOPEN,
                    ChatEventTypeEnum::SPAM,
                    ChatEventTypeEnum::NOT_SPAM,
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($event) => $this->formatEvent($event, $metaChatSession));

            return [
                'success'      => true,
                'events'       => $events,
                'chat_session' => $metaChatSession,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get chat activity',
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function asController(MetaChatSession $metaChatSession): array
    {
        return $this->handle($metaChatSession);
    }

    private function formatEvent($event, MetaChatSession $metaChatSession): array
    {
        $formatted = [
            'id'                   => $event->id,
            'event_type'           => $event->event_type->value,
            'event_label'          => ChatEventTypeEnum::labels()[$event->event_type->value] ?? $event->event_type->value,
            'event_icon'           => ChatEventTypeEnum::stateIcon()[$event->event_type->value] ?? [],
            'created_at'           => $event->created_at,
            'created_at_formatted' => $event->created_at->format('Y-m-d H:i:s'),
            'created_at_relative'  => $event->created_at->diffForHumans(),
            'actor'                => null,
            'details'              => [],
        ];

        if ($event->actor_type) {
            $actor = [
                'id'   => null,
                'name' => 'System',
                'type' => $event->actor_type->value,
            ];

            if ($event->actor_type === ChatActorTypeEnum::AGENT) {
                $chatAgent = ChatAgent::with('user')->find($event->actor_id);
                if ($chatAgent) {
                    $actor['id']      = $chatAgent->id;
                    $actor['name']    = $chatAgent->user->contact_name ?? 'Agent';
                    $actor['user_id'] = $chatAgent->user_id;
                }
            } elseif ($event->actor_type === ChatActorTypeEnum::GUEST) {
                $actor['name'] = $metaChatSession->guest_identifier ?? 'Guest';
            }

            $formatted['actor'] = $actor;
        }

        $payload = $event->payload ?? [];

        $formatted['details'] = match ($event->event_type) {
            ChatEventTypeEnum::OPEN     => ['description' => 'Chat session was created'],
            ChatEventTypeEnum::CLOSE    => ['description' => 'Chat session was closed'],
            ChatEventTypeEnum::REOPEN   => ['description' => 'Chat session was reopened'],
            ChatEventTypeEnum::SPAM     => ['description' => 'Chat session was marked as spam'],
            ChatEventTypeEnum::NOT_SPAM => ['description' => 'Chat session was removed from spam'],
            ChatEventTypeEnum::PRIORITY => $this->formatPriorityEvent($payload),
            ChatEventTypeEnum::TRANSFER_TO_AGENT => $this->formatTransferEvent($payload),
            default => [],
        };

        return $formatted;
    }

    private function formatPriorityEvent(array $payload): array
    {
        $values   = $payload['values'] ?? [];
        $priority = $values['priority'] ?? 'unknown';

        return [
            'description'       => 'Chat session priority was updated to '.$priority,
            'priority'          => $priority,
            'priority_previous' => $payload['priority_previous'] ?? '',
            'priority_current'  => $payload['priority_current'] ?? '',
        ];
    }

    private function formatTransferEvent(array $payload): array
    {
        $agentIds = array_filter([
            $payload['from_agent_id'] ?? null,
            $payload['to_agent_id'] ?? null,
        ]);

        $agents = ChatAgent::with('user')
            ->whereIn('id', $agentIds)
            ->get()
            ->keyBy('id');

        $getName = fn ($id) => $agents[$id]?->user?->contact_name ?? 'Agent';

        return [
            'description'     => 'Chat session was transferred',
            'from_agent_name' => $getName(Arr::get($payload, 'from_agent_id')),
            'to_agent_name'   => $getName(Arr::get($payload, 'to_agent_id')),
        ];
    }

    public function jsonResponse(array $result): JsonResponse
    {
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'An error occurred',
                'error'   => $result['error'] ?? 'Exception',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'chat_session' => [
                    'id'   => $result['chat_session']->id,
                    'ulid' => $result['chat_session']->ulid,
                ],
                'activities' => $result['events']->values()->toArray(),
            ],
        ]);
    }
}

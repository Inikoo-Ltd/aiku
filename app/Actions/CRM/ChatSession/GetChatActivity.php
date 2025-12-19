<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use App\Models\CRM\WebUser;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;

class GetChatActivity
{
    use AsAction;

    public function handle(ChatSession $chatSession): array
    {
        try {
            $events = $chatSession->chatEvents()
                ->whereIn('event_type', [
                    ChatEventTypeEnum::OPEN,
                    ChatEventTypeEnum::TRANSFER_TO_AGENT,
                    ChatEventTypeEnum::PRIORITY,
                    ChatEventTypeEnum::CLOSE,
                    ChatEventTypeEnum::GUEST_PROFILE,
                ])
                ->with(['actor'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($event) use ($chatSession) {
                    return $this->formatEvent($event, $chatSession);
                });

            return [
                'success' => true,
                'events' => $events,
                'chat_session' => $chatSession
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get chat activity',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function asController(ChatSession $chatSession)
    {
        return $this->handle($chatSession);
    }


    private function formatEvent($event, $chatSession): array
    {
        $formatted = [
            'id' => $event->id,
            'event_type' => $event->event_type->value,
            'event_label' => ChatEventTypeEnum::labels()[$event->event_type->value] ?? $event->event_type->value,
            'event_icon' => ChatEventTypeEnum::stateIcon()[$event->event_type->value] ?? [],
            'created_at' => $event->created_at->addHours(8),
            'created_at_formatted' => $event->created_at->addHours(8)->format('Y-m-d H:i:s'),
            'created_at_relative' => $event->created_at->addHours(8)->diffForHumans(),
            'actor' => null,
            'details' => [],
        ];

        if ($event->actor_type) {
            $actor = [
                'id' => null,
                'name' => 'System',
                'type' => $event->actor_type->value,
            ];

            switch ($event->actor_type->value) {
                case ChatActorTypeEnum::GUEST->value:
                    $actor['id'] = null;
                    $actor['name'] = $chatSession->guest_identifier ?? 'Guest';
                    break;

                case ChatActorTypeEnum::AGENT->value:
                    $chatAgent = ChatAgent::with(['user'])->find($event->actor_id);

                    if ($chatAgent) {
                        $actor['id'] = $chatAgent->id;
                        $actor['name'] = $chatAgent->user->contact_name ??
                            'Agent';
                        $actor['user_id'] = $chatAgent->user_id;
                    } else {
                        $actor['id'] = null;
                        $actor['name'] = null;
                    }
                    break;

                case ChatActorTypeEnum::USER->value:
                    $webUser = WebUser::find($event->actor_id);

                    if ($webUser) {
                        $actor['id'] = $webUser->id;
                        $actor['name'] = $webUser->contact_name ??
                            $webUser->name ??
                            'User';
                    } else {
                        $actor['id'] = null;
                        $actor['name'] = null;
                    }
                    break;

                case ChatActorTypeEnum::SYSTEM->value:
                    $actor['id'] = null;
                    $actor['name'] = 'System';
                    break;
            }

            $formatted['actor'] = $actor;
        }

        switch ($event->event_type) {
            case ChatEventTypeEnum::OPEN:
                $formatted['details'] = $this->formatOpenEvent($event, $chatSession);
                break;

            case ChatEventTypeEnum::TRANSFER_TO_AGENT:
                $formatted['details'] = $this->formatTransferEvent($event);
                break;

            case ChatEventTypeEnum::PRIORITY:
                $formatted['details'] = $this->formatPriorityEvent($event);
                break;

            case ChatEventTypeEnum::CLOSE:
                $formatted['details'] = $this->formatCloseEvent();
                break;

            case ChatEventTypeEnum::GUEST_PROFILE:
                $formatted['details'] = $this->formatGuestProfileEvent($event, $chatSession);
                break;
        }

        return $formatted;
    }


    private function formatGuestProfileEvent($event, ChatSession $chatSession): array
    {
        $payload = $event->payload ?? [];

        return [
            'description' => 'Guest profile was submitted ',
            'name' => $payload['name'] ?? 'Unknown Name',
            'email' => $payload['email'] ?? 'Unknown Email',
            'phone' => $payload['phone'] ?? 'Unknown Phone',
            'guest_identifier' => $chatSession->guest_identifier ?? 'Guest',
        ];
    }

    private function formatOpenEvent($event, ChatSession $chatSession): array
    {
        $payload = $event->payload ?? [];

        return [
            'description' => 'Chat session was created',
            'is_guest' => $payload['is_guest'] ?? false,
            'ip_address' => $payload['ip_address'] ?? 'Unknown IP',
            'guest_identifier' => $chatSession->guest_identifier ?? 'Guest',
        ];
    }

    private function formatPriorityEvent($event): array
    {
        $payload = $event->payload ?? [];
        $values = $payload['values'] ?? [];
        $priority = $values['priority'] ?? 'unknown';

        return [
            'description' => 'Chat session priority was updated to ' . $priority,
            'priority' => $priority,
        ];
    }


    private function formatCloseEvent(): array
    {
        return [
            'description' => 'Chat session was closed',
        ];
    }

    private function formatTransferEvent($event): array
    {
        $payload = $event->payload ?? [];
        $agentIds = array_filter([
            $payload['from_agent_id'] ?? null,
            $payload['to_agent_id'] ?? null,
        ]);

        $agents = ChatAgent::with(['user'])
            ->whereIn('id', $agentIds)
            ->get()
            ->keyBy('id');

        $getName = fn ($id) => $agents[$id]?->user?->contact_name
            ?? $agents[$id]?->user?->name
            ?? 'Agent';

        return [
            'description' => 'Chat session was transferred',
            'from_agent_name' => $getName($payload['from_agent_id'] ?? null),
            'to_agent_name' => $getName($payload['to_agent_id'] ?? null),
        ];
    }




    public function jsonResponse($result): JsonResponse
    {

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'An error occurred',
                'error' =>  $result['error'] ?? 'Exception'
            ], 400);
        }

        $events = $result['events'];
        $chatSession = $result['chat_session'];

        return response()->json([
            'success' => true,
            'data' => [
                'chat_session' => [
                    'id' => $chatSession->id,
                    'ulid' => $chatSession->ulid,
                ],
                'activities' => $events->values()->toArray(),
            ]
        ]);
    }
}

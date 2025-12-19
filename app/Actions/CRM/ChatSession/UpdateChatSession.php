<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;

class UpdateChatSession
{
    use AsAction;

    public function rules(): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'rating'   => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'user_id'  => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }


    public function handle(ChatSession $chatSession, array $data): array
    {
        $updatedFields = [];
        $originalPriority = $chatSession->priority->value;

        $context = [];
        if (array_key_exists('priority', $data)) {
            $context['priority_previous'] = $originalPriority;
            $context['priority_current']  = $data['priority'];
            $updatedFields['priority'] = $data['priority'];
            $chatSession->priority = $data['priority'];
        }

        if (array_key_exists('rating', $data)) {
            $chatSession->rating = $data['rating'];
            $updatedFields['rating'] = $data['rating'];
        }

        $actorTypeStr = 'system';
        $actorId = null;

        if (array_key_exists('user_id', $data) && !empty($data['user_id'])) {
            $agent = ChatAgent::where('user_id', $data['user_id'])->first();
            if ($agent) {
                $actorTypeStr = 'agent';
                $actorId = $agent->id;
            } else {
                $actorTypeStr = 'user';
                $actorId = (int) $data['user_id'];
            }
        }

        if (!empty($updatedFields)) {

            $chatSession->save();

            $this->logUpdateChatSessionEvent(
                $chatSession,
                $actorTypeStr,
                $actorId,
                $updatedFields,
                $context
            );
        }

        return $updatedFields;
    }


    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $updatedFields = $this->handle($chatSession, $validated);

        if (empty($updatedFields)) {
            return response()->json([
                'success' => false,
                'message' => 'No fields were updated.',
            ], 400);
        }

        $message = collect(array_keys($updatedFields))
            ->map(fn ($field) => match ($field) {
                'priority' => 'Priority updated successfully',
                'rating' => 'Rating updated successfully',
                default => ucfirst($field) . ' updated successfully'
            })
            ->implode(', ');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'updated_fields' => $updatedFields
            ]
        ]);
    }


    protected function logUpdateChatSessionEvent(
        ChatSession $chatSession,
        string $actorType,
        ?int $actorId,
        array $updatedFields,
        array $context = []
    ): void {

        $actorType = match ($actorType) {
            'agent', ChatActorTypeEnum::AGENT->value => ChatActorTypeEnum::AGENT,
            'user', ChatActorTypeEnum::USER->value => ChatActorTypeEnum::USER,
            default => ChatActorTypeEnum::GUEST
        };

        $eventType = match (true) {
            isset($updatedFields['priority']) => ChatEventTypeEnum::PRIORITY,
            isset($updatedFields['rating'])   => ChatEventTypeEnum::RATING,
            default => null
        };

        if ($eventType === null) {
            return;
        }

        $payload = [
            'chat_session_id'  => $chatSession->id,
            'updated_by_type'  => $actorType->value,
            'updated_by_id'    => $actorId,
            'updated_fields'   => array_keys($updatedFields),
            'values'           => $updatedFields,
            'timestamp'        => now()->toISOString(),
        ];

        if (!empty($context)) {
            $payload = array_merge($payload, $context);
        }

        StoreChatEvent::make()->customEvent(
            $chatSession,
            $eventType,
            $actorType,
            $actorId,
            $payload
        );
    }
}

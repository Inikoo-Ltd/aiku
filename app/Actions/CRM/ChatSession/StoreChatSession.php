<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;

class StoreChatSession
{
    use AsAction;

    public function rules(): array
    {
        return [
            'web_user_id'      => ['nullable', 'exists:web_users,id'],
            'language_id'      => ['required', 'exists:languages,id'],
            'guest_identifier' => ['nullable', 'string', 'max:255'],
            'ai_model_version' => ['nullable', 'string', 'max:50'],
            'priority'         => ['required', Rule::enum(ChatPriorityEnum::class)],
            'ulid'             => ['sometimes', 'string', 'size:26', 'unique:chat_sessions,ulid'],
        ];
    }


    public function asController(ActionRequest $request): ChatSession
    {
        $validated = $request->validated();

        return $this->handle($validated);
    }


    public function handle(array $modelData): ChatSession
    {
        DB::beginTransaction();

        try {
            $isGuest = empty($modelData['web_user_id']);

            $guestIdentifier = $isGuest
                ? ($modelData['guest_identifier'] ?? 'guest_' . random_int(10000, 99999))
                : null;

            $chatSessionData = [
                'ulid'            => $modelData['ulid'] ?? Str::ulid(),
                'web_user_id'     => $modelData['web_user_id'] ?? null,
                'status'          => ChatSessionStatusEnum::WAITING->value,
                'guest_identifier' => $guestIdentifier,
                'language_id'     => $modelData['language_id'],
                'priority'        => $modelData['priority'],
                'ai_model_version' => $modelData['ai_model_version'] ?? 'default',
            ];

            $chatSession = ChatSession::create($chatSessionData);

            $this->logSessionOpen($chatSession, $modelData, $isGuest, $guestIdentifier);

            DB::commit();
            return $chatSession;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create chat session', [
                'error' => $e->getMessage(),
                'data'  => $modelData
            ]);

            throw $e;
        }
    }


    public function jsonResponse(ChatSession $chatSession): ChatSessionResource
    {
        return ChatSessionResource::make($chatSession)
            ->additional([
                'success' => true,
                'message' => 'Chat session started successfully',
            ]);
    }


    protected function logSessionOpen(ChatSession $chatSession, array $data, bool $isGuest, ?string $guestIdentifier): void
    {
        try {
            $actorType = $isGuest ? ChatActorTypeEnum::GUEST : ChatActorTypeEnum::USER;
            $actorId   = $isGuest ? null : $data['web_user_id'];

            $eventPayload = [
                'ip_address'          => request()->ip(),
                'user_agent'          => request()->userAgent(),
                'guest_identifier'    => $guestIdentifier,
                'language_id'         => $data['language_id'],
                'priority'            => $data['priority'],
                'is_guest'            => $isGuest,
            ];

            StoreChatEvent::make()->openSession($chatSession, $actorType, $actorId, $eventPayload);
        } catch (Exception $e) {
            Log::warning('Failed to log chat session open event', [
                'session_id' => $chatSession->id,
                'error'      => $e->getMessage()
            ]);
        }
    }
}

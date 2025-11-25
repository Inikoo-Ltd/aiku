<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\OrgAction;
use App\Models\CRM\WebUser;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatEvent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;

class StoreChatSession extends OrgAction
{
    use AsAction;

     public function handle(array $modelData): ChatSession
    {
        $isGuest = empty($modelData['web_user_id']);

        $guestIdentifier = $isGuest
            ? ($modelData['guest_identifier'] ?? 'guest_' . Str::random(16))
            : null;

        $chatSession = ChatSession::create([
            'ulid' => $modelData['ulid'] ?? Str::ulid(),
            'web_user_id' => $modelData['web_user_id'] ?? null,
            'status' => ChatSessionStatusEnum::WAITING->value,
            'guest_identifier' => $guestIdentifier,
            'language_id' => $modelData['language_id'] ?? 68,
            'priority' => $modelData['priority'] ?? ChatPriorityEnum::NORMAL->value,
            'ai_model_version' => $modelData['ai_model_version'] ?? 'default',
        ]);

        $this->logSessionOpen($chatSession, $modelData, $isGuest, $guestIdentifier);

        return $chatSession;
    }

    public function rules(): array
    {
        return [
            'web_user_id' => [
                'nullable',
                'exists:web_users,id'
            ],
            'language_id' => [
                'required',
                'exists:languages,id'
            ],
            'guest_identifier' => [
                'nullable',
                'string',
                'max:255'
            ],
            'ai_model_version' => [
                'nullable',
                'string',
                'max:50'
            ],
            'priority' => [
                'required',
                Rule::enum(ChatPriorityEnum::class)
            ],
            'ulid' => [
                'sometimes',
                'string',
                'size:26',
                'unique:chat_sessions,ulid'
            ],
        ];
    }

    public function asController(Request $request): JsonResponse
    {
        $webUserId = $this->getAuthenticatedWebUserId();

        if ($webUserId) {
            $request->merge(['web_user_id' => $webUserId]);
        }

        $chatSession = $this->handle($request->validated());

        return $this->jsonResponse($chatSession);
    }

    protected function getAuthenticatedWebUserId(): ?int
    {
        $webUserGuards = ['retina', 'web'];

        foreach ($webUserGuards as $guard) {
            if (auth()->guard($guard)->check()) {
                $user = auth()->guard($guard)->user();
                if ($user instanceof WebUser) {
                    return $user->id;
                }
            }
        }

        return null;
    }

    protected function logSessionOpen(ChatSession $chatSession, array $data, bool $isGuest, ?string $guestIdentifier): void
    {
        $actorType = $isGuest ? ChatActorTypeEnum::GUEST : ChatActorTypeEnum::USER;
        $actorId = $isGuest ? null : $data['web_user_id'];

        ChatEvent::create([
            'chat_session_id' => $chatSession->id,
            'event_type' => ChatEventTypeEnum::OPEN->value,
            'actor_type' => $actorType->value,
            'actor_id' => $actorId,
            'payload' => [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'guest_identifier' => $guestIdentifier,
                'language_id' => $data['language_id'] ?? 68,
                'priority' => $data['priority'] ?? ChatPriorityEnum::NORMAL->value,
                'is_guest' => $isGuest,
                'authenticated_guard' => $this->getCurrentWebUserGuard(),
            ],
        ]);
    }

    protected function getCurrentWebUserGuard(): ?string
    {
        $guards = ['retina', 'web'];

        foreach ($guards as $guard) {
            if (auth()->guard($guard)->check()) {
                $user = auth()->guard($guard)->user();
                if ($user instanceof WebUser) {
                    return $guard;
                }
            }
        }

        return null;
    }


    public function jsonResponse(ChatSession $chatSession): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Chat session started successfully',
            'data' => [
                'ulid' => $chatSession->ulid,
                'status' => $chatSession->status,
                'is_guest' => is_null($chatSession->web_user_id),
                'guest_identifier' => $chatSession->guest_identifier,
                'created_at' => $chatSession->created_at,
            ]
        ]);
    }


    public function htmlResponse(ChatSession $chatSession): JsonResponse
    {
        return $this->jsonResponse($chatSession);
    }









}

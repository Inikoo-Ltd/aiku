<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use App\Actions\OrgAction;
use App\Models\CRM\WebUser;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatEvent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use Illuminate\Validation\ValidationException;
use App\Actions\CRM\ChatSession\StoreChatEvent;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;

class StoreChatSession extends OrgAction
{
    use AsAction;

       public function handle(array $modelData): ChatSession
    {
        DB::beginTransaction();

        try {
            $isGuest = empty($modelData['web_user_id']);

            $guestIdentifier = $isGuest
                ? ($modelData['guest_identifier'] ?? 'guest_' . random_int(10000, 99999))
                : null;

            $chatSessionData = [];

            data_set($chatSessionData, 'ulid', $modelData['ulid'] ?? Str::ulid());
            data_set($chatSessionData, 'web_user_id', $modelData['web_user_id'] ?? null);
            data_set($chatSessionData, 'status', ChatSessionStatusEnum::WAITING->value);
            data_set($chatSessionData, 'guest_identifier', $guestIdentifier);
            data_set($chatSessionData, 'language_id', $modelData['language_id']);
            data_set($chatSessionData, 'priority', $modelData['priority']);
            data_set($chatSessionData, 'ai_model_version', $modelData['ai_model_version'] ?? 'default');


            $chatSession = ChatSession::create($chatSessionData);

            $this->logSessionOpen($chatSession, $modelData, $isGuest, $guestIdentifier);

            DB::commit();

            return $chatSession;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create chat session', [
                'error' => $e->getMessage(),
                'data' => $modelData
            ]);

            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'web_user_id' => ['nullable', 'exists:web_users,id'],
            'language_id' => ['required', 'exists:languages,id'],
            'guest_identifier' => ['nullable', 'string', 'max:255'],
            'ai_model_version' => ['nullable', 'string', 'max:50'],
            'priority' => ['required', Rule::enum(ChatPriorityEnum::class)],
            'ulid' => ['sometimes', 'string', 'size:26', 'unique:chat_sessions,ulid'],
        ];
    }

    public function asController(Request $request): ChatSession
    {
        $validated = $request->validate($this->rules());

        $webUserId = $this->getAuthenticatedWebUserId();
        if ($webUserId) {
            $validated['web_user_id'] = $webUserId;
        }

        return $this->handle($validated);
    }

    public function jsonResponse(ChatSession $chatSession): ChatSessionResource
    {
        return ChatSessionResource::make($chatSession)
            ->additional([
                'success' => true,
                'message' => 'Chat session started successfully',
            ]);
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
        try {
            $actorType = $isGuest ? ChatActorTypeEnum::GUEST : ChatActorTypeEnum::USER;
            $actorId = $isGuest ? null : $data['web_user_id'];

            $eventPayload = [];

            data_set($eventPayload, 'ip_address', request()->ip());
            data_set($eventPayload, 'user_agent', request()->userAgent());
            data_set($eventPayload, 'guest_identifier', $guestIdentifier);
            data_set($eventPayload, 'language_id', $data['language_id']);
            data_set($eventPayload, 'priority', $data['priority']);
            data_set($eventPayload, 'is_guest', $isGuest);
            data_set($eventPayload, 'authenticated_guard', $this->getCurrentWebUserGuard());

            StoreChatEvent::make()->openSession(
                $chatSession,
                $actorType,
                $actorId,
                $eventPayload
            );
        } catch (Exception $e) {
            Log::warning('Failed to log chat session open event', [
                'session_id' => $chatSession->id,
                'error' => $e->getMessage()
            ]);
        }
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

}
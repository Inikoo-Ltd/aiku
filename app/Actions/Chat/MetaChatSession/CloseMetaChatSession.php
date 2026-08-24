<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class CloseMetaChatSession
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(
        MetaChatSession $metaChatSession,
        ?int $actorId = null,
        ChatActorTypeEnum $actorType = ChatActorTypeEnum::AGENT,
        array $additionalData = []
    ): MetaChatSession {
        return DB::transaction(function () use ($metaChatSession, $actorId, $actorType, $additionalData) {

            $closedBy = match ($actorType) {
                ChatActorTypeEnum::AGENT => ChatSessionClosedByTypeEnum::AGENT,
                ChatActorTypeEnum::USER,
                ChatActorTypeEnum::GUEST => ChatSessionClosedByTypeEnum::USER,
                default                  => ChatSessionClosedByTypeEnum::SYSTEM,
            };

            $metaChatSession->update([
                'status'    => ChatSessionStatusEnum::CLOSED->value,
                'closed_by' => $closedBy->value,
                'closed_at' => now(),
            ]);

            $activeAssignments = $metaChatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->get();

            foreach ($activeAssignments as $assignment) {
                $assignment->update([
                    'status'      => ChatAssignmentStatusEnum::RESOLVED->value,
                    'resolved_at' => now(),
                ]);
            }

            $closedByLabel = match ($actorType) {
                ChatActorTypeEnum::AGENT => 'agent',
                ChatActorTypeEnum::USER  => 'user',
                ChatActorTypeEnum::GUEST => 'guest',
                default                  => 'system',
            };

            // ponytail: no meta broadcast exists yet; dispatch here when one lands
            StoreMetaChatMessage::run($metaChatSession, [
                'message_text' => "Chat session has been closed by $closedByLabel",
                'message_type' => ChatMessageTypeEnum::TEXT->value,
                'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
                'is_read'      => true,
                'read_at'      => now(),
                'delivered_at' => now(),
            ]);

            $this->logCloseEvent($metaChatSession, $actorId, $actorType, $activeAssignments, $additionalData);

            return $metaChatSession->fresh();
        });
    }

    /**
     * @throws \Throwable
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function asController(string $organisation, MetaChatSession $metaChatSession): RedirectResponse
    {
        $agent = $this->getCurrentAgent();

        if (!$agent) {
            throw ValidationException::withMessages([
                'message' => __('Only authenticated agents can close chats'),
            ]);
        }

        if ($metaChatSession->status === ChatSessionStatusEnum::CLOSED) {
            throw ValidationException::withMessages([
                'message' => __('Chat session is already closed'),
            ]);
        }

        try {
            $this->handle($metaChatSession, $agent->id);
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'message' => $e->getMessage(),
            ]);
        }

        return back()->setStatusCode(303);
    }

    public function getCurrentAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }

    protected function logCloseEvent(
        MetaChatSession $metaChatSession,
        ?int $actorId,
        ChatActorTypeEnum $actorType,
        Collection $assignments,
        array $additionalData = []
    ): void {
        $payload = [];

        if ($assignments->isNotEmpty()) {
            $payload['assignments'] = $assignments->map(function ($assignment) {
                return [
                    'assignment_id'       => $assignment->id,
                    'assigned_agent_id'   => $assignment->chat_agent_id,
                    'assigned_at'         => $assignment->assigned_at?->toISOString(),
                    'assignment_duration' => $assignment->assigned_at?->diffInMinutes(now()),
                ];
            })->toArray();
        }

        $payload = array_merge($payload, $additionalData);

        StoreMetaChatEvent::make()->closeSession(
            $metaChatSession,
            $actorType,
            $actorId,
            $payload,
        );
    }
}

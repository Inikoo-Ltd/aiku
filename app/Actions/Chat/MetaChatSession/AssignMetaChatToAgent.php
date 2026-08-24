<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatAssignment;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class AssignMetaChatToAgent
{
    use AsAction;

    protected function getCurrentAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }

    protected function getActiveAssignment(MetaChatSession $metaChatSession): ?MetaChatAssignment
    {
        /** @var MetaChatAssignment|null $assignment */
        $assignment = $metaChatSession->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->first();

        return $assignment;
    }

    public function handle(MetaChatSession $metaChatSession, ChatAgent $agent, string $note): MetaChatAssignment
    {
        $activeAssignment = $this->getActiveAssignment($metaChatSession);

        if ($activeAssignment) {
            $activeAssignment->update([
                'chat_agent_id' => $agent->id,
                'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
                'note'          => $note,
                'assigned_at'   => now(),
            ]);
        } else {
            /** @var MetaChatAssignment $activeAssignment */
            $activeAssignment = $metaChatSession->assignments()->create([
                'meta_channel_id' => $metaChatSession->meta_channel_id,
                'chat_agent_id'   => $agent->id,
                'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
                'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
                'note'            => $note,
                'assigned_at'     => now(),
            ]);
        }

        $metaChatSession->update(['status' => ChatSessionStatusEnum::ACTIVE->value]);

        return $activeAssignment;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function assignToSelf(string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can assign chats'),
            ], 403);
        }

        $activeAssignment = $this->getActiveAssignment($metaChatSession);

        if ($activeAssignment && $activeAssignment->chat_agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => __('Chat session already assigned to another agent'),
                'data'    => [
                    'assignment_id'       => $activeAssignment->id,
                    'assigned_agent_id'   => $activeAssignment->chat_agent_id,
                    'assigned_agent_name' => $activeAssignment->chatAgent?->user?->contact_name ?? 'Unknown',
                ],
            ], 409);
        }

        return $this->respond($metaChatSession, $agent, $activeAssignment, 'self_assign');
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function takeOver(string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can take over chats'),
            ], 403);
        }

        return $this->respond($metaChatSession, $agent, $this->getActiveAssignment($metaChatSession), 'take_over');
    }

    protected function respond(
        MetaChatSession $metaChatSession,
        ChatAgent $agent,
        ?MetaChatAssignment $activeAssignment,
        string $actionType
    ): JsonResponse {
        if ($activeAssignment && $activeAssignment->chat_agent_id === $agent->id) {
            return response()->json([
                'success' => true,
                'message' => __('Chat session already assigned to you'),
                'data'    => [
                    'assignment_id'       => $activeAssignment->id,
                    'session_ulid'        => $metaChatSession->ulid,
                    'session_status'      => $metaChatSession->status->value,
                    'assigned_agent_id'   => $agent->id,
                    'assigned_agent_name' => $agent->user?->contact_name ?? 'Unknown',
                    'action_type'         => $actionType,
                ],
            ]);
        }

        try {
            $assignment = $this->handle(
                $metaChatSession,
                $agent,
                $actionType === 'take_over' ? 'Taken over by agent' : 'Assigned via assign-to-self'
            );
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $actionType === 'take_over'
                ? __('Chat taken over successfully')
                : __('Chat session assigned to you successfully'),
            'data'    => [
                'assignment_id'       => $assignment->id,
                'session_ulid'        => $metaChatSession->ulid,
                'session_status'      => $metaChatSession->status->value,
                'assigned_agent_id'   => $agent->id,
                'assigned_agent_name' => $agent->user?->contact_name ?? 'Unknown',
                'action_type'         => $actionType,
            ],
        ]);
    }
}

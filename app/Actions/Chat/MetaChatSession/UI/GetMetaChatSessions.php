<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession\UI;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\MetaChatSessionListResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaChatSessions
{
    use AsAction;

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                'in:' . implode(',', array_column(ChatSessionStatusEnum::cases(), 'value'))
            ],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [
                'string',
                'in:' . implode(',', array_column(ChatSessionStatusEnum::cases(), 'value'))
            ],
            'assigned_to_me' => ['sometimes', 'integer'],
            'is_spam'        => ['sometimes', 'boolean'],
            'highlighted'    => ['sometimes', 'boolean'],
            'trashed'        => ['sometimes', 'boolean'],
            'include_spam'   => ['sometimes', 'boolean'],
            'view_team'       => ['sometimes', 'boolean'],
            'page'            => ['sometimes', 'integer', 'min:1'],
            'channel'         => ['sometimes', 'string', 'max:50'],
            'limit'           => ['sometimes', 'integer', 'min:1', 'max:50'],
            'customer_id'     => ['sometimes', 'integer', 'exists:customers,id'],
            'search'          => ['sometimes', 'string', 'max:100'],
            'organisation_id' => ['sometimes', 'integer', 'exists:organisations,id'],
            'shop_id'         => ['sometimes', 'integer', 'exists:shops,id'],
        ];
    }

    public function asController(ActionRequest $request)
    {
        return $this->handle($request->validated());
    }

    /**
     * Meta sessions never carry a `waiting` status: they are stored as `active` and stay there.
     * Waiting means "nobody has picked it up yet", so it is derived from the absence of an
     * active assignment. Only `closed` maps onto the status column.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, string>  $requestedStatuses
     */
    protected function applyStatusFilter($query, array $requestedStatuses): void
    {
        $query->where(function ($outer) use ($requestedStatuses) {
            foreach ($requestedStatuses as $status) {
                $outer->orWhere(function ($q) use ($status) {
                    match ($status) {
                        ChatSessionStatusEnum::WAITING->value => $q
                            ->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
                            ->whereDoesntHave('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value)),
                        ChatSessionStatusEnum::ACTIVE->value => $q
                            ->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
                            ->whereHas('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value)),
                        default => $q->where('status', $status),
                    };
                });
            }
        });
    }

    public function handle(array $filters = [])
    {
        $query = MetaChatSession::with([
            'messages' => function ($q) {
                $q->latest()->limit(1);
            },
            'lastVisitorMessage' => function ($q) {
                $q->latest()->limit(1);
            },
            'events' => function ($q) {
                $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1);
            },
            'customer',
            'shop',
            'assignments.chatAgent.user'
        ])
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->where('is_read', false)
                        ->whereIn('sender_type', [
                            ChatSenderTypeEnum::GUEST->value,
                            ChatSenderTypeEnum::USER->value,
                        ]);
                }
            ])
            ->orderByRaw('COALESCE(last_visitor_message_at, last_agent_message_at, created_at) DESC');

        $requestedStatuses = (array) ($filters['statuses'] ?? (isset($filters['status']) ? [$filters['status']] : []));

        if ($requestedStatuses) {
            $this->applyStatusFilter($query, $requestedStatuses);
        }

        $isTrashView = !empty($filters['trashed']);
        $isSpamView  = !empty($filters['is_spam']) && !$isTrashView;

        if ($isTrashView) {
            $query->onlyTrashed();
        }

        if ($isTrashView || $isSpamView) {
            $viewAgent = !empty($filters['assigned_to_me'])
                ? ChatAgent::where('user_id', (int) $filters['assigned_to_me'])->first()
                : null;

            $query->whereIn('shop_id', $viewAgent ? $viewAgent->shops()->pluck('shops.id')->all() : []);
        }

        if (!$isTrashView && empty($filters['include_spam'])) {
            $query->where('is_spam', $isSpamView);
        }

        // Additive: keeps the normal status and assignment filters, just narrows to
        // the highlighted threads.
        if (!empty($filters['highlighted'])) {
            $query->where('is_highlighted', true);
        }

        if (!$isSpamView && !$isTrashView && !empty($filters['assigned_to_me'])) {
            $userId       = (int) $filters['assigned_to_me'];
            $currentAgent = ChatAgent::where('user_id', $userId)->first();

            if ($currentAgent) {
                $shopIds = $currentAgent->shops()->pluck('shops.id');

                if (!empty($filters['view_team'])) {
                    $teamAgentIds = ChatAgent::whereHas('shops', function ($q) use ($shopIds) {
                        $q->whereIn('shops.id', $shopIds);
                    })->where('id', '!=', $currentAgent->id)->pluck('id');

                    $isClosed         = in_array('closed', $requestedStatuses);
                    $assignmentStatus = $isClosed
                        ? ChatAssignmentStatusEnum::RESOLVED->value
                        : ChatAssignmentStatusEnum::ACTIVE->value;

                    $query->whereHas('assignments', function ($assignmentQ) use ($teamAgentIds, $assignmentStatus) {
                        $assignmentQ->whereIn('chat_agent_id', $teamAgentIds)
                            ->where('status', $assignmentStatus);
                    });
                } else {
                    $query->where(function ($q) use ($currentAgent, $shopIds) {
                        $q->where(function ($sub) use ($shopIds) {
                            $sub->whereIn('shop_id', $shopIds)
                                ->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
                                ->whereDoesntHave('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value));
                        })->orWhereHas('assignments', function ($assignmentQ) use ($currentAgent) {
                            $assignmentQ->where('chat_agent_id', $currentAgent->id);
                        });
                    });
                }
            }
        }

        if (!empty($filters['organisation_id'])) {
            $organisationId = (int) $filters['organisation_id'];
            $query->whereHas('shop', function ($q) use ($organisationId) {
                $q->where('organisation_id', $organisationId);
            });
        }

        if (!empty($filters['shop_id'])) {
            $query->where('shop_id', (int) $filters['shop_id']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(meta_chat_sessions.guest_identifier COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(meta_chat_sessions.phone_number COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereHas('customer', function ($q2) use ($term) {
                        $q2->whereRaw('LOWER(contact_name COLLATE "C") LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(name COLLATE "C") LIKE ?', ["%{$term}%"]);
                    });
            });
        }

        return $query->paginate($filters['limit'] ?? 20);
    }

    public function jsonResponse($sessions): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Meta chat sessions retrieved successfully',
            'data' => [
                'sessions'   => MetaChatSessionListResource::collection($sessions),
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page'     => $sessions->perPage(),
                    'total'        => $sessions->total(),
                    'last_page'    => $sessions->lastPage(),
                    'has_more'     => $sessions->hasMorePages(),
                ]
            ]
        ]);
    }
}

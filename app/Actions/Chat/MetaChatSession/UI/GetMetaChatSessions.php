<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\WithChatMessageSearch;
use App\Actions\Chat\WithUnclaimedChatSessions;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Actions\Chat\ChatSession\GetChatReplyPromise;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\MetaChatSessionListResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaChatSessions
{
    use AsAction;
    use WithChatAgentAuthorisation;
    use WithUnclaimedChatSessions;
    use WithChatMessageSearch;

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                'in:' . implode(',', array_column(ChatSessionStatusEnum::cases(), 'value'))
            ],
            'closed_period' => ['sometimes', 'string', 'in:'.implode(',', GetChatSessions::CLOSED_PERIODS)],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [
                'string',
                'in:' . implode(',', array_column(ChatSessionStatusEnum::cases(), 'value'))
            ],
            'assigned_to_me' => ['sometimes', 'integer'],
            'is_spam'        => ['sometimes', 'boolean'],
            'pairs'          => ['sometimes', 'array'],
            'pairs.*'        => ['string', 'regex:/^[a-z]+:(customer|guest)$/'],
            'highlighted'    => ['sometimes', 'boolean'],
            'unclaimed'      => ['sometimes', 'boolean'],
            'trashed'        => ['sometimes', 'boolean'],
            'include_spam'   => ['sometimes', 'boolean'],
            'view_team'       => ['sometimes', 'boolean'],
            'page'            => ['sometimes', 'integer', 'min:1'],
            'channel'         => ['sometimes', 'string', 'max:50'],
            'limit'           => ['sometimes', 'integer', 'min:1', 'max:50'],
            'customer_id'     => ['sometimes', 'integer', 'exists:customers,id'],
            'ulid'            => ['sometimes', 'string', 'max:26'],
            'search'          => ['sometimes', 'string', 'max:100'],
            'organisation_id' => ['sometimes', 'integer', 'exists:organisations,id'],
            'shop_id'         => ['sometimes', 'integer', 'exists:shops,id'],
            'shop_ids'        => ['sometimes', 'array'],
            'shop_ids.*'      => ['integer', 'exists:shops,id'],
            'agent_ids'       => ['sometimes', 'array'],
            'agent_ids.*'     => ['integer'],
        ];
    }

    public function asController(ActionRequest $request)
    {
        return $this->handle($this->chatFiltersScopedTo($request->user(), $request->validated()));
    }

    /**
     * Meta sessions never carry a `waiting` status: they are stored as `active` and stay there.
     * Waiting means "nobody has picked it up yet", so it is derived from the absence of an
     * active assignment. Only `closed` maps onto the status column.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, string>  $requestedStatuses
     */
    protected function applyStatusFilter($query, array $requestedStatuses, array $filters = []): void
    {
        $query->where(function ($outer) use ($requestedStatuses, $filters) {
            foreach ($requestedStatuses as $status) {
                $outer->orWhere(function ($q) use ($status, $filters) {
                    match ($status) {
                        ChatSessionStatusEnum::WAITING->value => $q
                            ->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
                            ->whereDoesntHave('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value)),
                        ChatSessionStatusEnum::ACTIVE->value => $q
                            ->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
                            ->whereHas('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value)),
                        ChatSessionStatusEnum::CLOSED->value => GetChatSessions::scopeClosedSince(
                            $q->where('status', $status),
                            GetChatSessions::closedSince($filters)
                        ),
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
            'assignments.chatAgent.user',
            'staffTasks' => fn ($q) => $q->open()->with('assignee'),
        ])
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->where('is_read', false)
                        ->whereIn('sender_type', [
                            ChatSenderTypeEnum::GUEST->value,
                            ChatSenderTypeEnum::USER->value,
                        ]);
                },
                'tickets as open_tickets_count' => function ($q) {
                    $q->whereNotIn('status', [TicketStatusEnum::RESOLVED->value, TicketStatusEnum::CANCELLED->value]);
                },
                'tickets as blocking_tickets_count' => function ($q) {
                    $q->where('blocks_source', true)
                        ->whereNotIn('status', [TicketStatusEnum::RESOLVED->value, TicketStatusEnum::CANCELLED->value]);
                },
            ]);

        if (GetChatSessions::oldestFirst($filters)) {
            $query->orderByRaw(GetChatReplyPromise::waitingSql('meta_chat_sessions'));
        }

        $query->orderByRaw('COALESCE(last_visitor_message_at, last_agent_message_at, created_at) '.(GetChatSessions::oldestFirst($filters) ? 'ASC' : 'DESC'));

        $requestedStatuses = (array) ($filters['statuses'] ?? (isset($filters['status']) ? [$filters['status']] : []));

        if ($requestedStatuses) {
            $this->applyStatusFilter($query, $requestedStatuses, $filters);
        }

        // WhatsApp carries the customer on the session itself rather than through a web user.
        // Only its own pairs say anything here; the other channels live in another table.
        $kinds = collect($filters['pairs'] ?? [])
            ->map(fn ($pair) => explode(':', (string) $pair, 2))
            ->filter(fn ($parts) => count($parts) === 2 && $parts[0] === 'whatsapp')
            ->map(fn ($parts) => $parts[1])
            ->unique()
            ->values()
            ->all();

        if ($kinds === ['customer']) {
            $query->whereNotNull('customer_id');
        } elseif ($kinds === ['guest']) {
            $query->whereNull('customer_id');
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

            $query->whereIn('shop_id', $viewAgent ? $this->shopIdsWorkedBy((int) $filters['assigned_to_me']) : []);
        }

        if (!$isTrashView && empty($filters['include_spam'])) {
            $query->where('is_spam', $isSpamView);
        }

        if (!empty($filters['unclaimed'])) {
            $this->scopeUnclaimedMetaChatSessions($query);
        }

        // Additive: keeps the normal status and assignment filters, just narrows to
        // the highlighted threads.
        if (!empty($filters['highlighted'])) {
            $query->where('is_highlighted', true);
        }

        if (!$isSpamView && !$isTrashView && empty($filters['unclaimed']) && !empty($filters['assigned_to_me'])) {
            $userId       = (int) $filters['assigned_to_me'];
            $currentAgent = ChatAgent::where('user_id', $userId)->first();

            if ($currentAgent) {
                $shopIds = $this->shopIdsWorkedBy($userId);

                $isClosed         = in_array('closed', $requestedStatuses);
                $assignmentStatus = $isClosed
                    ? ChatAssignmentStatusEnum::RESOLVED->value
                    : ChatAssignmentStatusEnum::ACTIVE->value;

                if (!empty($filters['ulid'])) {
                    // Opening one chat by link: it only has to belong to a shop this
                    // agent handles, whoever is currently on it. Which tab and which of
                    // my/team it belongs to is then decided from what comes back.
                    $query->whereIn('shop_id', $shopIds);
                } elseif (!empty($filters['view_team'])) {
                    $query->whereIn('shop_id', $shopIds);
                    $this->scopeHeldByColleague($query, $currentAgent->id, $assignmentStatus, $isClosed);
                } else {
                    // "Mine" means currently held by me. Matching any assignment row
                    // regardless of status would keep threads that have since been
                    // handed to another agent, listed under that agent's name.
                    $query->where(function ($q) use ($currentAgent, $shopIds, $assignmentStatus) {
                        $q->where(function ($sub) use ($shopIds) {
                            $sub->whereIn('shop_id', $shopIds)
                                ->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
                                ->whereDoesntHave('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value));
                        })->orWhereHas('assignments', function ($assignmentQ) use ($currentAgent, $assignmentStatus) {
                            $assignmentQ->where('chat_agent_id', $currentAgent->id)
                                ->where('status', $assignmentStatus);
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

        if (array_key_exists('allowed_shop_ids', $filters)) {
            $query->whereIn('shop_id', $filters['allowed_shop_ids']);
        }

        // Whoever oversees asks for what one colleague is holding. It has to be asked of the
        // database: picked out of the page already loaded, it finds nothing past the first twenty.
        if (!empty($filters['agent_ids'])) {
            $agentIds = array_map('intval', (array) $filters['agent_ids']);
            $query->whereHas('assignments', fn ($a) => $a->whereIn('chat_agent_id', $agentIds)
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value));
        }

        if (!empty($filters['shop_id'])) {
            $query->where('shop_id', (int) $filters['shop_id']);
        }

        if (!empty($filters['shop_ids'])) {
            $query->whereIn('shop_id', array_map('intval', (array) $filters['shop_ids']));
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Opening a chat from a link has to find it wherever it sits in the list, not
        // only within the first page.
        if (!empty($filters['ulid'])) {
            $query->where('ulid', $filters['ulid']);
        }

        if (!empty($filters['search'])) {
            $term             = mb_strtolower($filters['search']);
            $matchingMessages = $this->messagesMatching(MetaChatMessage::class, $filters['search'], $filters['allowed_shop_ids'] ?? []);

            $query->where(function ($q) use ($term, $matchingMessages) {
                $q->whereRaw('LOWER(meta_chat_sessions.guest_identifier COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(meta_chat_sessions.phone_number COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereHas('customer', function ($q2) use ($term) {
                        $q2->whereRaw('LOWER(contact_name COLLATE "C") LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(name COLLATE "C") LIKE ?', ["%{$term}%"]);
                    })
                    ->orWhereHas('messages', fn ($messages) => $messages->whereIn('meta_chat_messages.id', $matchingMessages));
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

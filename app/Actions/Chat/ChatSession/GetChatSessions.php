<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\WebUser;

class GetChatSessions
{
    use AsAction;
    use WithChatAgentAuthorisation;

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
            'view_team'       => ['sometimes', 'boolean'],
            'is_spam'         => ['sometimes', 'boolean'],
            'is_rubbish'      => ['sometimes', 'boolean'],
            'highlighted'     => ['sometimes', 'boolean'],
            'trashed'         => ['sometimes', 'boolean'],
            'limit'           => ['sometimes', 'integer', 'min:1', 'max:50'],
            'web_user_id'     => ['sometimes', 'integer', 'exists:web_users,id'],
            'ulid'            => ['sometimes', 'string', 'max:26'],
            'search'          => ['sometimes', 'string', 'max:100'],
            'organisation_id' => ['sometimes', 'integer', 'exists:organisations,id'],
            'shop_id'         => ['sometimes', 'integer', 'exists:shops,id'],
            'shop_ids'        => ['sometimes', 'array'],
            'shop_ids.*'      => ['integer', 'exists:shops,id'],
            'agent_ids'       => ['sometimes', 'array'],
            'agent_ids.*'     => ['integer'],
            'pairs'           => ['sometimes', 'array'],
            'pairs.*'         => ['string', 'regex:/^[a-z]+:(customer|guest)$/'],
        ];
    }

    public function asController(ActionRequest $request)
    {
        $filters = $request->validated();
        $user    = $request->user();

        if ($user instanceof WebUser) {
            $filters['web_user_id'] = $user->id;
            $filters['include_spam'] = true;
            unset($filters['assigned_to_me'], $filters['view_team'], $filters['is_spam'], $filters['trashed'], $filters['highlighted']);
        } else {
            $filters = $this->chatFiltersScopedTo($user, $filters);
        }

        return $this->handle($filters);
    }

    public function handle(array $filters = [])
    {

        $query = ChatSession::with([
            'messages' => function ($q) {
                $q->latest()->limit(1);
            },
            'chatEvents' => function ($q) {
                $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1);
            },
            'webUser',
            'shop',
            'assignments.chatAgent.user'
        ])
            ->whereHas('messages')
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
            ])
            ->withLastMessageTime()
            ->orderBy('last_message_at', 'desc');


        if (array_key_exists('allowed_shop_ids', $filters)) {
            $query->whereIn('shop_id', $filters['allowed_shop_ids']);
        }

        $statuses = (array) ($filters['statuses'] ?? (isset($filters['status']) ? [$filters['status']] : []));

        if ($statuses !== []) {
            $query->where(function ($outer) use ($statuses) {
                foreach ($statuses as $status) {
                    $outer->orWhere(function ($q) use ($status) {
                        $q->where('status', $status);

                        if ($status === ChatSessionStatusEnum::CLOSED->value) {
                            self::scopeClosedToday($q);
                        }
                    });
                }
            });
        }

        $isTrashView   = !empty($filters['trashed']);
        $isSpamView    = !empty($filters['is_spam']) && !$isTrashView;
        $isRubbishView = !empty($filters['is_rubbish']) && !$isTrashView && !$isSpamView;
        $includeSpam   = !empty($filters['include_spam']);

        // Rubbish stays out of every list but its own. The mark hides the conversation, it does
        // not change its status, so taking it off puts it back where it was.
        if (!$isTrashView) {
            $query->where('is_rubbish', $isRubbishView);
        }

        // Trash view: only soft-deleted sessions, scoped to the agent's shops.
        if ($isTrashView) {
            $query->onlyTrashed();

            $trashAgent = !empty($filters['assigned_to_me'])
                ? $this->getCurrentAgent((int) $filters['assigned_to_me'])
                : null;

            $query->whereIn('shop_id', $trashAgent ? $this->shopIdsWorkedBy((int) $filters['assigned_to_me']) : []);
        } elseif (!$includeSpam) {
            $query->where('is_spam', $isSpamView);
        }

        if ($isSpamView) {
            $spamAgent = !empty($filters['assigned_to_me'])
                ? $this->getCurrentAgent((int) $filters['assigned_to_me'])
                : null;

            $query->whereIn('shop_id', $spamAgent ? $this->shopIdsWorkedBy((int) $filters['assigned_to_me']) : []);
        }

        // Highlight view is additive: it keeps the normal status/assignment filters
        // (waiting/active/closed + my/team) and just restricts to highlighted sessions.
        if (!empty($filters['highlighted'])) {
            $query->where('is_highlighted', true);
        }

        if (!$isSpamView && !$isTrashView && !empty($filters['assigned_to_me'])) {
            $userId       = (int) $filters['assigned_to_me'];
            $currentAgent = $this->getCurrentAgent($userId);

            if ($currentAgent) {
                $shopIds = $this->shopIdsWorkedBy($userId);

                $requestedStatuses = (array) ($filters['statuses'] ?? ($filters['status'] ? [$filters['status']] : []));
                $isClosed          = in_array('closed', $requestedStatuses);
                $assignmentStatus  = $isClosed
                    ? ChatAssignmentStatusEnum::RESOLVED->value
                    : ChatAssignmentStatusEnum::ACTIVE->value;

                if (!empty($filters['ulid'])) {
                    // Opening one chat by link: it only has to belong to a shop this
                    // agent handles, whoever is currently on it. Which tab and which of
                    // my/team it belongs to is then decided from what comes back.
                    $query->whereIn('shop_id', $shopIds);
                } elseif (!empty($filters['view_team'])) {
                    $teamAgentIds = $this->agentIdsCovering($shopIds, $currentAgent->id);

                    $query->whereHas('assignments', function ($assignmentQ) use ($teamAgentIds, $assignmentStatus) {
                        $assignmentQ->whereIn('chat_agent_id', $teamAgentIds)
                            ->where('status', $assignmentStatus);
                    });
                } else {
                    // "Mine" means currently held by me. Matching any assignment row
                    // regardless of status would keep threads that have since been
                    // handed to another agent, listed under that agent's name.
                    $query->where(function ($q) use ($currentAgent, $shopIds, $assignmentStatus) {
                        $q->where(function ($sub) use ($shopIds) {
                            $sub->whereIn('shop_id', $shopIds)
                                ->where('status', ChatSessionStatusEnum::WAITING);
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

        // Each pair is one channel for one kind of sender, and any set of them may be asked
        // for at once. They have to be matched as pairs rather than as two separate lists:
        // wanting email from strangers and website from customers is not the same as wanting
        // both channels from both, which is what filtering the two dimensions apart would give.
        //
        // A conversation belongs to a customer when it is tied to their web user; everything
        // else is a stranger, which on email means a supplier or a robot.
        $pairs = $this->sessionPairs($filters);

        if ($pairs !== []) {
            $query->where(function ($outer) use ($pairs) {
                foreach ($pairs as [$channel, $kind]) {
                    $outer->orWhere(function ($inner) use ($channel, $kind) {
                        $inner->where('channel', $channel);

                        $kind === 'customer'
                            ? $inner->whereNotNull('web_user_id')
                            : $inner->whereNull('web_user_id');
                    });
                }
            });
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

        if (isset($filters['web_user_id'])) {
            $query->whereIn('web_user_id', (array) $filters['web_user_id']);
        }

        // Opening a chat from a link has to find it wherever it sits in the list, not
        // only within the first page.
        if (!empty($filters['ulid'])) {
            $query->where('ulid', $filters['ulid']);
        }

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(chat_sessions.guest_identifier COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereHas('webUser', function ($q2) use ($term) {
                        $q2->whereRaw('LOWER(username COLLATE "C") LIKE ?', ["%{$term}%"])
                            ->orWhereHas('customer', function ($q3) use ($term) {
                                $q3->whereRaw('LOWER(contact_name COLLATE "C") LIKE ?', ["%{$term}%"]);
                            });
                    });
            });
        }

        return $query->paginate($filters['limit'] ?? 20);
    }

    /**
     * Closed conversations are kept forever, so the list and the capsule only ever mean the
     * ones closed today; older ones are found through search or the reports.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopeClosedToday($query): void
    {
        $table = $query->getModel()->getTable();

        $query->whereRaw("coalesce({$table}.closed_at, {$table}.updated_at) >= ?", [now()->startOfDay()]);
    }

    protected function getCurrentAgent(int $userId): ?ChatAgent
    {
        return ChatAgent::where('user_id', $userId)->first();
    }

    /**
     * The channel and sender pairs this list is limited to, WhatsApp left out because it lives
     * in its own table. No pairs at all means no limit, which is what the housekeeping views
     * and the notification counters ask for.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{0: string, 1: string}>
     */
    public static function sessionPairs(array $filters): array
    {
        return collect($filters['pairs'] ?? [])
            ->map(fn ($pair) => explode(':', (string) $pair, 2))
            ->filter(fn ($parts) => count($parts) === 2 && $parts[0] !== 'whatsapp')
            ->values()
            ->all();
    }

    public function jsonResponse($sessions): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Chat sessions retrieved successfully',
            'data' => [
                'sessions'   => ChatSessionListResource::collection($sessions),
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

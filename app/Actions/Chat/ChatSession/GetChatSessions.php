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
use App\Actions\Chat\WithChatMessageSearch;
use App\Actions\Chat\WithUnclaimedChatSessions;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
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
    use WithUnclaimedChatSessions;
    use WithChatMessageSearch;
    /**
     * How far back the closed list reaches, the same words the tickets board uses for its own
     * columns. Today is the default: the queue's closed capsule means what was finished today,
     * and anything longer is somebody going back through the history on purpose.
     */
    public const array CLOSED_PERIODS = ['today', '24h', '1w', '1m', '1y', 'all'];

    public const string DEFAULT_CLOSED_PERIOD = 'today';

    /**
     * The email inbox's tabs. Unanswered is what the list row already calls unanswered: the
     * customer wrote last and nobody set it aside to wait for them. Everything else that is open
     * is waiting on the customer.
     */
    public const array REPLY_STATES = ['unanswered', 'waiting_on_customer'];

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
            'closed_period' => ['sometimes', 'string', 'in:' . implode(',', self::CLOSED_PERIODS)],
            'assigned_to_me' => ['sometimes', 'integer'],
            'view_team'       => ['sometimes', 'boolean'],
            'is_spam'         => ['sometimes', 'boolean'],
            'is_rubbish'      => ['sometimes', 'boolean'],
            'highlighted'     => ['sometimes', 'boolean'],
            'promised'        => ['sometimes', 'boolean'],
            'label_id'        => ['sometimes', 'integer'],
            'offline'         => ['sometimes', 'boolean'],
            'carrier'         => ['sometimes', 'boolean'],
            'colleague'       => ['sometimes', 'boolean'],
            'ds_kind'         => ['sometimes', 'string', 'in:'.implode(',', FlagUrgentChatRequest::KINDS)],
            'unclaimed'       => ['sometimes', 'boolean'],
            'reply_state'     => ['sometimes', 'string', 'in:'.implode(',', self::REPLY_STATES)],
            'unread'          => ['sometimes', 'boolean'],
            'order'           => ['sometimes', 'string', 'in:newest'],
            'trashed'        => ['sometimes', 'boolean'],
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

    /**
     * When the customer started waiting: their first message after our last reply. A customer
     * chasing with "any update?" keeps their place in the queue instead of going to the back.
     * When we spoke last, the conversation's latest message stands in.
     */
    /**
     * The customer wrote last: a held conversation like this is back in Waiting, still held, so
     * a colleague sees it when the holder is busy or away.
     */
    public static function unansweredSql(string $sessionTable): string
    {
        return "$sessionTable.last_visitor_message_at > coalesce($sessionTable.last_agent_message_at, '-infinity')";
    }

    public static function waitingSinceSql(string $sessionTable, string $messageTable, string $sessionKey): string
    {
        $visitorTypes = "'".ChatSenderTypeEnum::GUEST->value."','".ChatSenderTypeEnum::USER->value."'";
        $agentType    = "'".ChatSenderTypeEnum::AGENT->value."'";

        return "coalesce((select min(wm.created_at) from $messageTable wm where wm.$sessionKey = $sessionTable.id
            and wm.sender_type in ($visitorTypes)
            and wm.created_at > coalesce((select max(am.created_at) from $messageTable am where am.$sessionKey = $sessionTable.id and am.sender_type = $agentType), '-infinity')),
            (select max(lm.created_at) from $messageTable lm where lm.$sessionKey = $sessionTable.id), $sessionTable.created_at)";
    }

    /**
     * A queue is worked from the top, so the conversation that has been waiting longest belongs
     * there: newest first is how a chat from Monday goes untouched for four days while one that
     * arrived after it is answered in two minutes.
     *
     * The bins are the other way round. Nobody works through spam, rubbish or the trash; they
     * are looked at to find what landed there a moment ago, and so is the mailbox's All mail.
     */
    public static function oldestFirst(array $filters): bool
    {
        return ($filters['order'] ?? null) !== 'newest'
            && empty($filters['is_spam']) && empty($filters['is_rubbish']) && empty($filters['trashed'])
            && !self::isClosedHistory($filters);
    }

    /**
     * Asking for closed conversations and nothing else is the history view: it is read to find
     * what was said to somebody weeks ago, so it reads newest first. Closed alongside waiting
     * or active is still part of the queue and stays oldest first with the rest of it.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function isClosedHistory(array $filters): bool
    {
        $statuses = (array) ($filters['statuses'] ?? (isset($filters['status']) ? [$filters['status']] : []));

        return array_values(array_unique($statuses)) === [ChatSessionStatusEnum::CLOSED->value];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function closedSince(array $filters): ?\Illuminate\Support\Carbon
    {
        return match ($filters['closed_period'] ?? self::DEFAULT_CLOSED_PERIOD) {
            '24h'   => now()->subDay(),
            '1w'    => now()->subWeek(),
            '1m'    => now()->subMonth(),
            '1y'    => now()->subYear(),
            'all'   => null,
            default => now()->startOfDay(),
        };
    }

    public function handle(array $filters = [])
    {

        $query = ChatSession::with([
            'messages' => function ($q) {
                $q->where('sender_type', '!=', ChatSenderTypeEnum::SYSTEM)->latest()->limit(1);
            },
            'chatEvents' => function ($q) {
                $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1);
            },
            'webUser',
            'shop',
            'activeUserLanguage',
            'userLanguage',
            'assignments.chatAgent.user',
            'staffTasks' => fn ($q) => $q->open()->with('assignee'),
            'labels:id,name,colour',
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
            ->withLastMessageTime();

        if (self::oldestFirst($filters)) {
            $query->selectRaw(self::waitingSinceSql('chat_sessions', 'chat_messages', 'chat_session_id').' as waiting_since')
                ->orderByRaw(FlagUrgentChatRequest::waitingSql('chat_sessions'))
                ->orderBy('waiting_since');
        } else {
            $query->orderBy('last_message_at', 'desc');
        }


        if (array_key_exists('allowed_shop_ids', $filters)) {
            $query->whereIn('shop_id', $filters['allowed_shop_ids']);
        }

        $statuses = (array) ($filters['statuses'] ?? (isset($filters['status']) ? [$filters['status']] : []));

        if ($statuses !== []) {
            $query->where(function ($outer) use ($statuses, $filters) {
                foreach ($statuses as $status) {
                    $outer->orWhere(function ($q) use ($status, $filters) {
                        $q->where('status', $status);

                        if ($status === ChatSessionStatusEnum::WAITING->value) {
                            $q->orWhere(fn ($held) => $held->where('status', ChatSessionStatusEnum::ACTIVE->value)->whereRaw(self::unansweredSql('chat_sessions')));
                        }

                        if ($status === ChatSessionStatusEnum::CLOSED->value) {
                            self::scopeClosedSince($q, self::closedSince($filters));
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

        // Couriers and colleagues are answered from their own folders and nowhere else.
        if (!$isTrashView) {
            $query->where('is_carrier', !empty($filters['carrier']))
                ->where('is_colleague', !empty($filters['colleague']));
        }

        // Trash view: only soft-deleted sessions, scoped to the agent's shops.
        if ($isTrashView) {
            $query->onlyTrashed();
            $query->whereIn('shop_id', $this->binShopIds($filters));
        } elseif (!$includeSpam) {
            $query->where('is_spam', $isSpamView);
        }

        if ($isSpamView) {
            $query->whereIn('shop_id', $this->binShopIds($filters));
        }

        // The unclaimed queue is the whole group's, so it takes no status, no my/team and no
        // shop: which shops the person asking works is exactly what let these go unanswered.
        if (!empty($filters['unclaimed'])) {
            $this->scopeUnclaimedChatSessions($query);
        }

        if (!empty($filters['ds_kind'])) {
            $query->where('metadata->'.FlagUrgentChatRequest::KIND_KEY, $filters['ds_kind']);
        }

        // Highlight view is additive: it keeps the normal status/assignment filters
        // (waiting/active/closed + my/team) and just restricts to highlighted sessions.
        if (!empty($filters['highlighted'])) {
            $query->where('is_highlighted', true);
        }

        if (!empty($filters['promised'])) {
            GetChatReplyPromise::scopeWaiting($query);
        }

        if (!empty($filters['unread'])) {
            self::scopeUnread($query);
        }

        if (!empty($filters['label_id'])) {
            $query->whereHas('labels', fn ($labelQuery) => $labelQuery->whereKey((int) $filters['label_id']));
        }

        // Left through the website's offline form while nobody was there to chat.
        if (!empty($filters['offline'])) {
            $query->whereHas('messages', fn ($messageQuery) => $messageQuery->where('metadata->is_offline_message', true));
        }

        if (!empty($filters['reply_state'])) {
            self::scopeReplyState($query, $filters['reply_state']);
        }

        if (!$isSpamView && !$isTrashView && empty($filters['unclaimed']) && !empty($filters['assigned_to_me'])) {
            $userId       = (int) $filters['assigned_to_me'];
            $currentAgent = $this->getCurrentAgent($userId);

            if ($currentAgent) {
                $shopIds = $this->shopIdsWorkedBy($userId);

                $isClosed         = in_array('closed', $statuses);
                $assignmentStatus = $isClosed
                    ? ChatAssignmentStatusEnum::RESOLVED->value
                    : ChatAssignmentStatusEnum::ACTIVE->value;

                if (!empty($filters['ulid']) || !empty($filters['carrier']) || !empty($filters['colleague'])) {
                    // Opening one chat by link: it only has to belong to a shop this
                    // agent handles, whoever is currently on it. Which tab and which of
                    // my/team it belongs to is then decided from what comes back. The
                    // Couriers and Colleagues folders are shared the same way: every
                    // agent on the shop sees them, whoever picked the conversation up.
                    $query->whereIn('shop_id', $shopIds);
                } elseif (!empty($filters['view_team'])) {
                    $query->whereIn('shop_id', $shopIds);
                    $this->scopeHeldByColleague($query, $currentAgent->id, $assignmentStatus, $isClosed);
                } else {
                    // "Mine" means currently held by me. Matching any assignment row
                    // regardless of status would keep threads that have since been
                    // handed to another agent, listed under that agent's name.
                    $query->where(function ($q) use ($currentAgent, $shopIds, $statuses) {
                        $q->where(function ($sub) use ($shopIds) {
                            $sub->whereIn('shop_id', $shopIds)
                                ->where('status', ChatSessionStatusEnum::WAITING);
                        });

                        self::orHeldBy($q, $currentAgent->id, $statuses);

                        if (in_array(ChatSessionStatusEnum::WAITING->value, $statuses, true)) {
                            $q->orWhere(fn ($held) => $held->whereIn('shop_id', $shopIds)
                                ->where('status', ChatSessionStatusEnum::ACTIVE->value)
                                ->whereRaw(self::unansweredSql('chat_sessions')));
                        }
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

        // One sender wherever they wrote: through the customer's logins, or as the address they
        // wrote from before anybody linked them. Our own code builds it; it is never a request field.
        if (isset($filters['contact'])) {
            $webUserIds = array_map('intval', $filters['contact']['web_user_ids'] ?? []);
            $emails     = array_values($filters['contact']['emails'] ?? []);

            $query->where(function ($q) use ($webUserIds, $emails) {
                $q->whereIn('web_user_id', $webUserIds);

                if ($emails !== []) {
                    $q->orWhereRaw("lower(metadata->>'email') in (".implode(',', array_fill(0, count($emails), '?')).')', $emails);
                }
            });
        }

        if (!empty($filters['except_ulid'])) {
            $query->where('ulid', '!=', $filters['except_ulid']);
        }

        // Opening a chat from a link has to find it wherever it sits in the list, not
        // only within the first page.
        if (!empty($filters['ulid'])) {
            $query->where('ulid', $filters['ulid']);
        }

        if (!empty($filters['search'])) {
            $term             = mb_strtolower($filters['search']);
            $matchingMessages = $this->messagesMatching(ChatMessage::class, $filters['search'], $filters['allowed_shop_ids'] ?? []);
            $orderCustomers   = $this->customersWithOrderReference($filters['search'], $filters['allowed_shop_ids'] ?? []);

            $query->where(function ($q) use ($term, $matchingMessages, $orderCustomers) {
                $q->whereRaw('LOWER(chat_sessions.guest_identifier COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereRaw(
                        "LOWER(concat_ws(' ', chat_sessions.metadata->>'email', chat_sessions.metadata->>'email_from', chat_sessions.metadata->>'email_from_name', chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_subject') COLLATE \"C\") LIKE ?",
                        ["%{$term}%"]
                    )
                    ->orWhereHas('webUser', function ($q2) use ($term, $orderCustomers) {
                        $q2->whereRaw('LOWER(username COLLATE "C") LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(email COLLATE "C") LIKE ?', ["%{$term}%"])
                            ->when($orderCustomers->isNotEmpty(), fn ($q3) => $q3->orWhereIn('customer_id', $orderCustomers))
                            ->orWhereHas('customer', function ($q3) use ($term) {
                                $q3->whereRaw('LOWER(contact_name COLLATE "C") LIKE ?', ["%{$term}%"])
                                    ->orWhereRaw('LOWER(name COLLATE "C") LIKE ?', ["%{$term}%"])
                                    ->orWhereRaw('LOWER(email COLLATE "C") LIKE ?', ["%{$term}%"]);
                            });
                    })
                    ->orWhereHas('messages', fn ($messages) => $messages->whereIn('chat_messages.id', $matchingMessages));
            });
        }

        // The page is the caller's, not the request's: merged lists ask every source for page one,
        // and Eloquent otherwise reads ?page= from the request and skips rows.
        return $query->paginate($filters['limit'] ?? 20, ['*'], 'page', $filters['page'] ?? null);
    }

    /**
     * Closed conversations are kept forever, so a list of them always says how far back it
     * reaches. The capsule counters mean today; the list itself is whatever period was picked.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopeClosedToday($query): void
    {
        self::scopeClosedSince($query, now()->startOfDay());
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopeClosedSince($query, ?\Illuminate\Support\Carbon $since): void
    {
        if (!$since) {
            return;
        }

        $table = $query->getModel()->getTable();

        $query->whereRaw("coalesce({$table}.closed_at, {$table}.updated_at) >= ?", [$since]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    /**
     * Held by this agent the way each status means it: an open conversation is one they hold now,
     * a closed one is one they finished. The search and All mail ask for both at once, and matching
     * only finished ones there left out every open conversation the agent was holding.
     *
     * @param  array<int, string>  $statuses
     */
    public static function orHeldBy($query, int $agentId, array $statuses): void
    {
        $closed = ChatSessionStatusEnum::CLOSED->value;
        $open   = array_values(array_diff($statuses ?: [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value], [$closed]));
        $heldBy = fn (string $assignmentStatus) => fn ($assignments) => $assignments->where('chat_agent_id', $agentId)->where('status', $assignmentStatus);

        if ($open !== []) {
            $query->orWhere(fn ($holding) => $holding->whereIn('status', $open)->whereHas('assignments', $heldBy(ChatAssignmentStatusEnum::ACTIVE->value)));
        }

        if (in_array($closed, $statuses, true)) {
            $query->orWhere(fn ($finished) => $finished->where('status', $closed)->whereHas('assignments', $heldBy(ChatAssignmentStatusEnum::RESOLVED->value)));
        }
    }

    public static function scopeReplyState($query, string $replyState): void
    {
        $table = $query->getModel()->getTable();

        $unanswered = function ($q) use ($table) {
            $q->whereNotNull("{$table}.last_visitor_message_at")
                ->where(fn ($last) => $last->whereNull("{$table}.last_agent_message_at")
                    ->orWhereColumn("{$table}.last_visitor_message_at", '>', "{$table}.last_agent_message_at"))
                ->whereRaw("{$table}.metadata->'".WaitForCustomerReply::KEY."'->>'until' is null");
        };

        $replyState === 'unanswered'
            ? $query->where($unanswered)
            : $query->whereNot($unanswered);
    }

    /**
     * Something the customer wrote that nobody has opened yet.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopeUnread($query): void
    {
        $query->whereHas('messages', fn ($messages) => $messages->where('is_read', false)
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value]));
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

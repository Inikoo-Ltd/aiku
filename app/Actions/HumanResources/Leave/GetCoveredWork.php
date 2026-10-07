<?php

namespace App\Actions\HumanResources\Leave;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\MetaChatAssignment;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Ticket;
use App\Models\Tasks\StaffTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The open work assigned to an absent colleague's users that whoever covers them has to pick up.
 */
class GetCoveredWork
{
    use AsObject;

    /**
     * ponytail: each kind of work stops at this many items, page it if someone ever leaves more behind.
     */
    public const int ITEMS_PER_KIND = 50;

    /**
     * @param Collection<int, int> $userIds
     *
     * @return array<int, array{key: string, label: string, icon: string, items: array<int, array{reference: string, title: string, status: string, url: string|null}>}>
     */
    public function handle(Collection $userIds): array
    {
        return collect($this->kinds($userIds))
            ->map(fn (array $kind) => [
                'key'   => $kind['key'],
                'label' => $kind['label'],
                'icon'  => $kind['icon'],
                'items' => collect($kind['queries'])
                    ->flatMap(fn (array $source) => $source['query']->limit(self::ITEMS_PER_KIND)->get()->map($source['item']))
                    ->all(),
            ])
            ->all();
    }

    /**
     * @param Collection<int, int> $userIds
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function counts(Collection $userIds): array
    {
        return collect($this->kinds($userIds))
            ->map(fn (array $kind) => [
                'key'   => $kind['key'],
                'label' => $kind['label'],
                'count' => collect($kind['queries'])->sum(fn (array $source) => $source['query']->count()),
            ])
            ->filter(fn (array $kind) => $kind['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, int> $userIds
     */
    private function kinds(Collection $userIds): array
    {
        $chatAgentIds = ChatAgent::withTrashed()->whereIn('user_id', $userIds)->pluck('id');

        return [
            ['key' => 'tasks', 'label' => __('Tasks'), 'icon' => 'fal fa-tasks', 'queries' => [$this->tasks($userIds)]],
            ['key' => 'tickets', 'label' => __('Tickets'), 'icon' => 'fal fa-life-ring', 'queries' => [$this->tickets($userIds)]],
            ['key' => 'chats', 'label' => __('Chats'), 'icon' => 'fal fa-comments', 'queries' => [$this->liveChats($chatAgentIds), $this->metaChats($chatAgentIds)]],
            ['key' => 'delivery_notes', 'label' => __('Picking and packing'), 'icon' => 'fal fa-dolly', 'queries' => [$this->deliveryNotes($userIds)]],
        ];
    }

    private function tasks(Collection $userIds): array
    {
        return [
            'query' => StaffTask::query()->open()
                ->whereIn('assignee_id', $userIds)
                ->orderByRaw('due_at asc nulls last, id asc'),
            'item'  => fn (StaffTask $task) => [
                'reference' => $task->reference,
                'title'     => $task->subject,
                'status'    => StaffTaskStatusEnum::labels()[$task->status->value],
                'url'       => route('grp.tasks.show', $task->reference),
            ],
        ];
    }

    private function tickets(Collection $userIds): array
    {
        return [
            'query' => Ticket::query()
                ->whereIn('assignee_id', $userIds)
                ->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED])
                ->orderByDesc('id'),
            'item'  => fn (Ticket $ticket) => [
                'reference' => $ticket->reference,
                'title'     => $ticket->subject,
                'status'    => TicketStatusEnum::labels()[$ticket->status->value],
                'url'       => route('grp.tickets.show', $ticket->reference),
            ],
        ];
    }

    private function liveChats(Collection $chatAgentIds): array
    {
        return [
            'query' => ChatAssignment::query()
                ->with(['chatSession.webUser.customer', 'chatSession.shop.organisation'])
                ->whereIn('chat_agent_id', $chatAgentIds)
                ->where('status', ChatAssignmentStatusEnum::ACTIVE)
                ->whereHas('chatSession', fn (Builder $session) => $session->where('status', '!=', ChatSessionStatusEnum::CLOSED))
                ->orderByDesc('id'),
            'item'  => function (ChatAssignment $assignment) {
                $session          = $assignment->chatSession;
                $organisationSlug = $session->shop?->organisation?->slug;

                return [
                    'reference' => __('Live chat'),
                    'title'     => $session->webUser?->customer?->name ?: ($session->webUser?->contact_name ?: ($session->guest_identifier ?? __('Guest'))),
                    'status'    => ChatSessionStatusEnum::labels()[$session->status->value],
                    'url'       => $organisationSlug ? route('grp.org.chat.inbox.conversation', ['organisation' => $organisationSlug, 'chatSession' => $session->ulid]) : null,
                ];
            },
        ];
    }

    private function metaChats(Collection $chatAgentIds): array
    {
        return [
            'query' => MetaChatAssignment::query()
                ->with(['metaChatSession.customer', 'metaChatSession.shop.organisation'])
                ->whereIn('chat_agent_id', $chatAgentIds)
                ->where('status', ChatAssignmentStatusEnum::ACTIVE)
                ->whereHas('metaChatSession', fn (Builder $session) => $session->where('status', '!=', ChatSessionStatusEnum::CLOSED))
                ->orderByDesc('id'),
            'item'  => function (MetaChatAssignment $assignment) {
                $session          = $assignment->metaChatSession;
                $organisationSlug = $session->shop?->organisation?->slug;

                return [
                    'reference' => __('WhatsApp'),
                    'title'     => $session->customer?->name ?: ($session->phone_number ?: ($session->guest_identifier ?? __('Guest'))),
                    'status'    => ChatSessionStatusEnum::labels()[$session->status->value],
                    'url'       => $organisationSlug ? route('grp.org.chat.inbox', ['organisation' => $organisationSlug]).'?channel=whatsapp&session='.$session->ulid : null,
                ];
            },
        ];
    }

    private function deliveryNotes(Collection $userIds): array
    {
        return [
            'query' => DeliveryNote::query()
                ->with(['customer', 'organisation', 'warehouse'])
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $picking) => $picking
                        ->whereIn('picker_user_id', $userIds)
                        ->whereIn('state', [DeliveryNoteStateEnum::QUEUED, DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]))
                    ->orWhere(fn (Builder $packing) => $packing
                        ->whereIn('packer_user_id', $userIds)
                        ->whereIn('state', [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED, DeliveryNoteStateEnum::PICKED, DeliveryNoteStateEnum::PACKING])))
                ->orderBy('id'),
            'item'  => fn (DeliveryNote $deliveryNote) => [
                'reference' => $deliveryNote->reference,
                'title'     => (string) $deliveryNote->customer?->name,
                'status'    => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value],
                'url'       => route('grp.org.warehouses.show.dispatching.delivery_notes.show', [$deliveryNote->organisation->slug, $deliveryNote->warehouse->slug, $deliveryNote->slug]),
            ],
        ];
    }
}

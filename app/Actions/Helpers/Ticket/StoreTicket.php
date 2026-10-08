<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketSourceChannelEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreTicket extends OrgAction
{
    private ?Ticket $linkSource = null;

    public function handle(Group $group, array $modelData): Ticket
    {
        $linkTicketId = Arr::pull($modelData, 'link_ticket_id');
        $linkType     = Arr::pull($modelData, 'link_type');

        $type = TicketTypeEnum::from(Arr::get($modelData, 'type') ?: ($linkTicketId ? TicketTypeEnum::ENGINEER->value : TicketTypeEnum::HELP->value));

        $number = DB::selectOne('SELECT nextval(?) AS number', [$type->sequence()])->number;

        data_set($modelData, 'group_id', $group->id);
        data_set($modelData, 'type', $type);
        data_set($modelData, 'number', $number);
        data_set($modelData, 'reference', $type->prefix().'-'.str_pad((string) $number, $type->numberPadding(), '0', STR_PAD_LEFT));

        $images = Arr::pull($modelData, 'images', []);
        Arr::forget($modelData, 'stay');
        if ($referenceUrl = Arr::pull($modelData, 'reference_url')) {
            data_set($modelData, 'data.reference_url', $referenceUrl);
        }

        $ticket = Ticket::create($modelData);
        $ticket->attachTicketImages($images);
        SyncTicketSlackAlert::run($ticket);
        NotifyTicketUsers::make()->raised($ticket);
        NotifyTicketUsers::make()->pushBadges($ticket);
        if ($ticket->kind === null || $ticket->module === null) {
            ClassifyTicket::dispatch($ticket);
        }

        if ($linkTicketId && $source = Ticket::where('group_id', $group->id)->find($linkTicketId)) {
            StoreTicketLink::make()->handle($source, ['linked_ticket_id' => $ticket->id, 'type' => $linkType ?: 'relates'], $this->actor());
        }

        return $ticket;
    }

    private function actor(): ?User
    {
        return $this->asAction ? null : request()->user();
    }

    public function rules(): array
    {
        return [
            'subject'         => ['required', 'string', 'max:255'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'type'            => ['sometimes', 'nullable', Rule::enum(TicketTypeEnum::class), $this->linkSource
                ? Rule::in([TicketTypeEnum::ENGINEER->value, TicketTypeEnum::HELP->value])
                : Rule::when(!$this->asAction && !Ticket::canChooseType(request()->user()), Rule::in([TicketTypeEnum::HELP->value]))],
            'link_ticket_id'  => ['sometimes', 'nullable', 'integer'],
            'link_type'       => ['sometimes', 'nullable', Rule::in(['relates', 'blocks', 'blocked_by', 'duplicates', 'duplicated_by'])],
            'kind'            => ['sometimes', 'nullable', Rule::enum(TicketKindEnum::class), Rule::when(!$this->asAction && !Ticket::canBeManagedBy(request()->user()), Rule::notIn(TicketKindEnum::internalValues()))],
            'module'          => ['sometimes', 'nullable', Rule::enum(TicketModuleEnum::class)],
            'tags'            => ['sometimes', 'array'],
            'is_confidential' => ['sometimes', 'boolean'],
            'blocks_source'   => ['sometimes', 'boolean'],
            'closes_source'   => ['sometimes', 'boolean'],
            'reporter_muted'  => ['sometimes', 'boolean'],
            'tags.*'            => ['string', 'max:64'],
            'priority'        => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'assignee_id'     => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
            'organisation_id' => ['sometimes', 'nullable', 'integer'],
            'shop_id'         => ['sometimes', 'nullable', 'integer'],
            'customer_id'     => ['sometimes', 'nullable', 'integer'],
            'reporter_type'   => ['sometimes', 'nullable', 'string'],
            'reporter_id'     => ['sometimes', 'nullable', 'integer'],
            'model_type'      => ['sometimes', 'nullable', 'string'],
            'model_id'        => ['sometimes', 'nullable', 'integer'],
            'source_type'     => ['sometimes', 'nullable', 'string'],
            'source_id'       => ['sometimes', 'nullable', 'integer'],
            'source_channel'  => ['sometimes', 'nullable', Rule::enum(TicketSourceChannelEnum::class)],
            'data'            => ['sometimes', 'array'],
            'images'          => ['sometimes', 'array', 'max:5'],
            'images.*'        => Ticket::ticketFileRules(),
            'stay'            => ['sometimes', 'boolean'],
            'reference_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
            'ticket_project_id' => ['sometimes', 'nullable', Rule::exists('ticket_projects', 'id')->where('group_id', $this->group->id)->whereNull('deleted_at')],
            'ticket_project_milestone_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function getValidationMessages(): array
    {
        return Ticket::ticketFileValidationMessages();
    }

    public function getValidationAttributes(): array
    {
        return Ticket::ticketFileValidationAttributes($this->get('images', []));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->filled('link_ticket_id')) {
            $this->linkSource = Ticket::where('group_id', group()->id)->find($request->integer('link_ticket_id'));

            return $this->linkSource?->canLinkBy($request->user()) ?? false;
        }

        return Ticket::canBeRaisedBy($request->user());
    }

    public function action(Group $group, array $modelData): Ticket
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    public function asController(ActionRequest $request): Ticket
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, [
            ...$this->validatedData,
            'reporter_type' => 'User',
            'reporter_id'   => $request->user()->id,
        ]);
    }

    public function htmlResponse(Ticket $ticket, ActionRequest $request): RedirectResponse
    {
        if ($request->boolean('stay')) {
            return back()->with('notification', ['status' => 'success', 'title' => $ticket->reference, 'description' => __('Bug reported, thank you')]);
        }

        return redirect()->route('grp.tickets.show', $ticket->reference);
    }
}

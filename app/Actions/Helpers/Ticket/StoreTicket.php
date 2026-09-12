<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithTicketsWriteGuard;
use App\Actions\Chat\Staff\StoreStaffConversation;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
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
    use WithTicketsWriteGuard;

    public function handle(Group $group, array $modelData): Ticket
    {
        $type = TicketTypeEnum::from(Arr::get($modelData, 'type', TicketTypeEnum::HELP->value));
        $this->guardTicketsWritable($type);

        $number = DB::selectOne('SELECT nextval(?) AS number', [$type->sequence()])->number;

        data_set($modelData, 'group_id', $group->id);
        data_set($modelData, 'type', $type);
        data_set($modelData, 'number', $number);
        data_set($modelData, 'reference', $type->prefix().'-'.$number);

        $images = Arr::pull($modelData, 'images', []);
        Arr::forget($modelData, 'stay');
        if ($referenceUrl = Arr::pull($modelData, 'reference_url')) {
            data_set($modelData, 'data.reference_url', $referenceUrl);
        }

        $ticket = Ticket::create($modelData);
        $ticket->attachTicketImages($images);
        $this->openStaffConversation($ticket);
        SyncTicketSlackAlert::run($ticket);

        return $ticket;
    }

    private function openStaffConversation(Ticket $ticket): void
    {
        if ($ticket->type !== TicketTypeEnum::HELP || !$ticket->reporter instanceof User) {
            return;
        }

        StoreStaffConversation::make()->handle($ticket->reporter, [
            'user_ids'     => array_filter([$ticket->assignee_id]),
            'name'         => $ticket->reference.' · '.$ticket->subject,
            'context_type' => 'Ticket',
            'context_id'   => $ticket->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'subject'         => ['required', 'string', 'max:255'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'type'            => ['sometimes', Rule::enum(TicketTypeEnum::class)],
            'kind'            => ['sometimes', 'nullable', Rule::enum(TicketKindEnum::class)],
            'module'          => ['sometimes', 'nullable', Rule::enum(TicketModuleEnum::class)],
            'tags'            => ['sometimes', 'array'],
            'is_confidential' => ['sometimes', 'boolean'],
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
            'data'            => ['sometimes', 'array'],
            'images'          => ['sometimes', 'array', 'max:5'],
            'images.*'        => ['image', 'max:10240'],
            'stay'            => ['sometimes', 'boolean'],
            'reference_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $request->user() !== null;
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

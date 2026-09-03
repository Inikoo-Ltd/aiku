<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreTicket extends OrgAction
{
    public function handle(Group $group, array $modelData): Ticket
    {
        $type = TicketTypeEnum::from(Arr::get($modelData, 'type', TicketTypeEnum::HELP->value));

        $number = DB::selectOne('SELECT nextval(?) AS number', [$type->sequence()])->number;

        data_set($modelData, 'group_id', $group->id);
        data_set($modelData, 'type', $type);
        data_set($modelData, 'number', $number);
        data_set($modelData, 'reference', $type->prefix().'-'.$number);

        return Ticket::create($modelData);
    }

    public function rules(): array
    {
        return [
            'subject'         => ['required', 'string', 'max:255'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'type'            => ['sometimes', Rule::enum(TicketTypeEnum::class)],
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

    public function htmlResponse(Ticket $ticket): RedirectResponse
    {
        return redirect()->route('grp.tickets.show', $ticket->reference);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Ticket;

use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\RetinaAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaTicket extends RetinaAction
{
    public function handle(WebUser $webUser, array $modelData): Ticket
    {
        return StoreTicket::make()->action($webUser->group, [
            ...$modelData,
            'type'            => TicketTypeEnum::CUSTOMER->value,
            'organisation_id' => $webUser->organisation_id,
            'shop_id'         => $webUser->shop_id,
            'customer_id'     => $webUser->customer_id,
            'reporter_type'   => 'WebUser',
            'reporter_id'     => $webUser->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority'    => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'images'      => ['sometimes', 'array', 'max:5'],
            'images.*'    => ['image', 'max:10240'],
        ];
    }

    public function action(WebUser $webUser, array $modelData): Ticket
    {
        $this->asAction = true;
        $this->setRawAttributes($modelData);

        return $this->handle($webUser, $this->validateAttributes());
    }

    public function asController(ActionRequest $request): Ticket
    {
        $this->initialisation($request);

        return $this->handle($this->webUser, $this->validatedData);
    }

    public function htmlResponse(Ticket $ticket): RedirectResponse
    {
        return redirect()->route('retina.dropshipping.tickets.show', $ticket->reference);
    }
}

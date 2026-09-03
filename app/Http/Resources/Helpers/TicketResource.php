<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'type'           => $this->type->value,
            'status'         => $this->status->value,
            'status_label'   => TicketStatusEnum::labels()[$this->status->value],
            'status_icon'    => TicketStatusEnum::stateIcon()[$this->status->value],
            'priority'       => $this->priority->value,
            'priority_label' => ChatPriorityEnum::labels()[$this->priority->value],
            'priority_icon'  => ChatPriorityEnum::stateIcon()[$this->priority->value],
            'subject'        => $this->subject,
            'description'    => $this->description,
            'reporter'       => $this->reporter?->contact_name ?: $this->reporter?->username,
            'assignee_id'    => $this->assignee_id,
            'assignee'       => $this->assignee?->contact_name ?: $this->assignee?->username,
            'customer'       => $this->customer?->name,
            'shop'           => $this->shop?->name,
            'model_type'     => $this->model_type,
            'model_id'       => $this->model_id,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'resolved_at'    => $this->resolved_at,
            'closed_at'      => $this->closed_at,
            'rating'         => $this->rating,
            'rating_comment' => $this->rating_comment,
            'images'         => $this->ticketImageSources(),
        ];
    }
}

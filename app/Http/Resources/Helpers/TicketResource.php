<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
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
            'kind'           => $this->kind?->value,
            'module'         => $this->module?->value,
            'module_label'   => $this->module ? TicketModuleEnum::labels()[$this->module->value] : null,
            'tags'           => $this->tags ?? [],
            'is_confidential' => (bool) $this->is_confidential,
            'qa_status'      => $this->qa_status?->value,
            'qa_status_label' => $this->qa_status ? TicketQaStatusEnum::labels()[$this->qa_status->value] : null,
            'qa_status_icon' => $this->qa_status ? TicketQaStatusEnum::stateIcon()[$this->qa_status->value] : null,
            'qa_user'        => $this->qaUser?->contact_name ?: $this->qaUser?->username,
            'qa_requested_at' => $this->qa_requested_at,
            'qa_checked_at'  => $this->qa_checked_at,
            'kind_label'     => $this->kind ? TicketKindEnum::labels()[$this->kind->value] : null,
            'parent'         => $this->model_type === 'Ticket' ? $this->model?->reference : null,
            'escalations'    => $this->escalations()->pluck('reference'),
            'staff_conversation_ulid' => $this->staffConversation?->ulid,
            'status'         => $this->status->value,
            'status_label'   => TicketStatusEnum::labels()[$this->status->value],
            'status_icon'    => TicketStatusEnum::stateIcon()[$this->status->value],
            'priority'       => $this->priority->value,
            'priority_label' => ChatPriorityEnum::labels()[$this->priority->value],
            'priority_icon'  => ChatPriorityEnum::stateIcon()[$this->priority->value],
            'subject'        => $this->subject,
            'description'    => $this->description,
            'reporter'       => $this->reporter?->contact_name ?: $this->reporter?->username,
            'reporter_short' => $this->reporter_type === 'User' ? $this->reporter?->username : ($this->reporter?->contact_name ?: $this->reporter?->username),
            'assignee_id'    => $this->assignee_id,
            'assignee'       => $this->assignee?->contact_name ?: $this->assignee?->username,
            'assignee_username' => $this->assignee?->username,
            'assignee_short' => $this->assignee ? strtok((string) ($this->assignee->contact_name ?: $this->assignee->username), ' ') : null,
            'assignee_avatar' => $this->assignee?->imageSources(48, 48),
            'customer'       => $this->customer?->name,
            'shop'           => $this->shop?->name,
            'model_type'     => $this->model_type,
            'model_id'       => $this->model_id,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'resolved_at'    => $this->resolved_at,
            'assigned_at'    => $this->assigned_at,
            'started_at'     => $this->started_at,
            'waiting_at'     => $this->waiting_at,
            'waiting_until'  => $this->waiting_until,
            'default_waiting_hours' => $this->defaultWaitingHours(),
            'closed_at'      => $this->closed_at,
            'rating'         => $this->rating,
            'rating_comment' => $this->rating_comment,
            'images'         => $this->ticketImageSources(),
            'attachments'    => $this->ticketAttachments(),
            'commits'        => collect(data_get($this->data, 'commits', []))->map(fn ($commit) => $commit + ['url' => config('services.github.repo') ? 'https://github.com/'.config('services.github.repo').'/commit/'.$commit['hash'] : null])->all(),
        ];
    }
}

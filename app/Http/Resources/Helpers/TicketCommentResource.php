<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketComment
 */
class TicketCommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'body'        => $this->body,
            'is_internal' => $this->is_internal,
            'is_lead_only' => $this->is_lead_only,
            'can_toggle_visibility' => $request->user() instanceof \App\Models\SysAdmin\User && \App\Models\Helpers\Ticket::canBeAssignedBy($request->user()),
            'is_staff'    => $this->author_type === 'User',
            'author'        => $this->author?->contact_name ?: $this->author?->username,
            'author_avatar' => $this->author?->imageSources(48, 48),
            'author_roles'  => $request->routeIs('retina.*') ? [] : $this->authorRoles(),
            'created_at'  => $this->created_at,
            'images'      => $this->ticketImageSources(),
            'attachments' => $this->ticketAttachments(),
            'can_edit'    => $request->user() instanceof \App\Models\SysAdmin\User && $this->isAuthoredBy($request->user()),
            'can_delete'  => $request->user() instanceof \App\Models\SysAdmin\User && $this->isAuthoredBy($request->user()),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function authorRoles(): array
    {
        $author = $this->author;

        if (!$author instanceof User) {
            return $author ? [['key' => 'customer', 'label' => __('Customer')]] : [];
        }

        if ($author->is_bot) {
            return [['key' => 'bot', 'label' => __('Bot')]];
        }

        $roles = [];

        if (Ticket::canBeAssignedBy($author)) {
            $roles[] = ['key' => 'lead_engineer', 'label' => __('Engineer')];
        } elseif (Ticket::canBeManagedBy($author)) {
            $roles[] = ['key' => 'engineer', 'label' => __('Engineer')];
        } elseif (Ticket::canCheckQa($author)) {
            $roles[] = ['key' => 'qa', 'label' => __('QA')];
        }

        if ($this->relationLoaded('ticket') && $this->ticket?->isReportedBy($author)) {
            $roles[] = ['key' => 'reporter', 'label' => __('Reporter')];
        }

        return $roles;
    }
}

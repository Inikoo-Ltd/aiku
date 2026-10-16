<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\Procurement\SupplierWechatConversation\SupplierWechatConversationKindEnum;
use App\Models\Procurement\SupplierWechatConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A messaging panel item, in the shape of a staff conversation plus what only a supplier chat
 * has: its organisation, the project it is filed under and where to read it from.
 *
 * @property SupplierWechatConversation $resource
 */
class SupplierWechatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;
        $project      = $conversation->project;

        return [
            'kind'                        => 'supplier_wechat',
            'ulid'                        => $conversation->ulid,
            'type'                        => $conversation->kind === SupplierWechatConversationKindEnum::GROUP ? 'group' : 'dm',
            'name'                        => $conversation->displayName(),
            'organisation_id'             => $conversation->organisation_id,
            'organisation_code'           => $conversation->organisation?->code,
            'participants'                => [],
            'context_type'                => null,
            'context_label'               => $project ? __('Project: :name', ['name' => $project->name]) : __('Supplier WeChat'),
            'context_url'                 => $project ? route('grp.projects.show', $project->slug) : null,
            'task'                        => null,
            'my_left_at'                  => null,
            'is_watching'                 => true,
            'last_message_at'             => $conversation->last_message_at?->toIso8601String(),
            'last_message'                => $conversation->last_message_snippet,
            'unread_count'                => (int) ($conversation->unread_count ?? 0),
            'has_mention'                 => false,
            'ticket_project_id'           => $conversation->ticket_project_id,
            'ticket_project_milestone_id' => $conversation->ticket_project_milestone_id,
            'routes'                      => [
                'messages' => route('grp.chat.supplier_wechat.messages.index', $conversation->ulid),
                'read'     => route('grp.chat.supplier_wechat.read', $conversation->ulid),
                'project'  => route('grp.chat.supplier_wechat.project.update', $conversation->ulid),
            ],
        ];
    }
}

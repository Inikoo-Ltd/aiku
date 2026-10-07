<?php

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\WebsiteDialog;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteDialogResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var WebsiteDialog $websiteDialog */
        $websiteDialog = $this;

        return [
            'id'                   => $websiteDialog->id,
            'ulid'                 => $websiteDialog->ulid,
            'name'                 => $websiteDialog->name,
            'template_code'        => $websiteDialog->template_code,
            'component'            => $websiteDialog->component,
            'fields'               => $websiteDialog->fields,
            'container_properties' => $websiteDialog->container_properties,
            'settings'             => $websiteDialog->settings,
            'state'                => $websiteDialog->state,
            'status'               => $websiteDialog->status,
            'status_icon'          => $websiteDialog->status->statusIcon()[$websiteDialog->status->value],
            'is_dirty'             => $websiteDialog->is_dirty,
            'is_published'         => $websiteDialog->published_layout !== null,
            'published_layout'     => $websiteDialog->published_layout,
            'published_message'    => $websiteDialog->published_message,
            'publisher'            => $websiteDialog->liveSnapshot?->publisher ? [
                'contact_name' => $websiteDialog->liveSnapshot->publisher->contact_name,
                'username'     => $websiteDialog->liveSnapshot->publisher->username,
            ] : null,
            'display_frequency'    => $websiteDialog->getDisplayFrequency(),
            'schedule_at'          => $websiteDialog->schedule_at,
            'schedule_finish_at'   => $websiteDialog->schedule_finish_at,
            'paused_until'         => $websiteDialog->paused_until,
            'paused_by'            => $websiteDialog->pausedBy?->name,
            'live_at'              => $websiteDialog->live_at,
            'closed_at'            => $websiteDialog->closed_at,
            'ready_at'             => $websiteDialog->ready_at,
            'created_at'           => $websiteDialog->created_at,
        ];
    }
}

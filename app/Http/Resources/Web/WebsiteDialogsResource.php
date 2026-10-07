<?php

namespace App\Http\Resources\Web;

use App\Enums\Web\WebsiteDialog\WebsiteDialogDisplayFrequencyEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\WebsiteDialog;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class WebsiteDialogsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var WebsiteDialog $websiteDialog */
        $websiteDialog = $this->resource;

        $settings = Arr::get($websiteDialog->published_layout ?? [], 'settings') ?? $websiteDialog->settings;

        return [
            'id'                => $websiteDialog->id,
            'ulid'              => $websiteDialog->ulid,
            'name'              => $websiteDialog->name,
            'created_at'        => $websiteDialog->created_at,
            'live_at'           => $websiteDialog->live_at,
            'closed_at'         => $websiteDialog->closed_at,
            'status'            => WebsiteDialogStatusEnum::statusIcon()[$websiteDialog->status->value],
            'show_pages'        => $websiteDialog->extractTargetPages($settings)['show_pages'],
            'display_frequency' => WebsiteDialogDisplayFrequencyEnum::labels()[$websiteDialog->getDisplayFrequency()] ?? $websiteDialog->getDisplayFrequency(),
            'publisher_name'    => $websiteDialog->liveSnapshot?->publisher?->contact_name,
            'paused_note'       => $this->getPausedNote($websiteDialog),
            'is_expired'        => $websiteDialog->schedule_finish_at?->isPast() ?? false,
        ];
    }

    protected function getPausedNote(WebsiteDialog $websiteDialog): ?string
    {
        if (!$websiteDialog->paused_by_website_dialog_id) {
            return null;
        }

        if (!$websiteDialog->paused_until) {
            return __('Paused by :name, turn it back on when you want it', ['name' => $websiteDialog->pausedBy?->name]);
        }

        return __('Paused by :name, back on :date', [
            'name' => $websiteDialog->pausedBy?->name,
            'date' => $websiteDialog->paused_until->format('d M Y H:i')
        ]);
    }
}

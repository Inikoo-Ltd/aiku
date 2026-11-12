<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Snapshot;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Snapshot
 * @property string|null $webpage_path
 * @property string|null $component_code
 * @property string $static_website_slug
 * @property string|null $publisher_name
 * @property int $size
 */
class StaticWebsiteSnapshotsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $isLive = $this->state === SnapshotStateEnum::LIVE;

        return [
            'id'             => $this->id,
            'created_at'     => $this->created_at,
            'is_live'        => $isLive,
            'state_icon'     => $isLive
                ? ['tooltip' => __('What the website shows now'), 'icon' => 'fal fa-broadcast-tower', 'class' => 'text-green-600']
                : ['tooltip' => __('Earlier version'), 'icon' => 'fal fa-history', 'class' => 'text-gray-400'],
            'part'           => $this->webpage_path ?? StaticWebsiteComponent::labelFor((string) $this->component_code),
            'part_route'     => [
                'name'       => $this->webpage_path !== null ? 'grp.marketing.info_websites.webpages.show' : 'grp.marketing.info_websites.components.show',
                'parameters' => [$this->static_website_slug, $this->webpage_path !== null ? $this->parent_id : $this->component_code],
            ],
            'comment'        => $this->comment,
            'publisher_name' => $this->publisher_name ?? __('System'),
            'size'           => round($this->size / 1024).' KB',
            'preview_url'    => route('grp.marketing.info_websites.snapshots.preview', [$this->static_website_slug, $this->id]),
            'restore_route'  => $isLive ? null : [
                'name'       => 'grp.models.static_website_snapshot.restore',
                'parameters' => [$this->id],
                'method'     => 'post',
            ],
        ];
    }
}

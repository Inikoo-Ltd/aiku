<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Actions\SysAdmin\WithLogRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $session_id
 * @property string $device_type
 * @property string $os
 * @property string $browser
 * @property string|null $country_code
 * @property string|null $city
 * @property int $page_views
 * @property int $duration_seconds
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property string|null $landing_page
 * @property string|null $exit_page
 * @property string|null $referrer_url
 * @property bool $is_bounce
 * @property bool $is_new_visitor
 */
class WebsiteVisitorResource extends JsonResource
{
    use WithLogRequest;

    public function toArray($request): array
    {
        $location = array_filter([
            $this->country_code,
            $this->city
        ]);

        return [
            'id'          => $this->id,
            'session_id'  => substr($this->session_id, 0, 8) . '...',
            'device_type' => [
                'label'   => ucfirst($this->device_type),
                'tooltip' => $this->device_type,
                'icon'    => $this->getDeviceIcon($this->device_type)
            ],
            'browser'     => [
                'label'   => $this->browser,
                'tooltip' => $this->browser,
                'icon'    => $this->getBrowserIcon($this->browser)
            ],
            'os'          => [
                'label'   => $this->os,
                'tooltip' => $this->os,
                'icon'    => $this->getPlatformIcon($this->os)
            ],
            'location'    => $location,
            'page_views'  => $this->page_views,
            'duration'    => $this->formatDuration($this->duration_seconds),
            'bounce'      => $this->is_bounce ? __('Yes') : __('No'),
            'first_seen_at' => $this->first_seen_at->diffForHumans(),
            'last_seen_at'  => $this->last_seen_at->diffForHumans(),
            'landing_page'  => $this->landing_page,
            'exit_page'     => $this->exit_page,
            'referrer_url'  => $this->referrer_url,
            'is_new_visitor' => $this->is_new_visitor,
        ];
    }

    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . $remainingSeconds . 's';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours . 'h ' . $remainingMinutes . 'm';
    }
}

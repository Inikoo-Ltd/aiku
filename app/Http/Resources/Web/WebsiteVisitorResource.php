<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Actions\SysAdmin\WithLogRequest;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
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
 * @property int|null $web_user_id
 * @property string|null $web_user_slug
 * @property string|null $customer_slug
 * @property string|null $web_user_contact_name
 * @property string|null $traffic_source_type
 * @property string|null $traffic_source_reference
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
            'web_user'    => $this->web_user_id ? [
                'slug'          => $this->web_user_slug,
                'customer_slug' => $this->customer_slug,
                'contact_name'  => $this->web_user_contact_name,
            ] : null,
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
            'traffic_source_type' => $this->traffic_source_type ? [
                'label'     => WebsiteVisitorChannelEnum::typeLabel($this->traffic_source_type),
                'channel'   => WebsiteVisitorChannelEnum::fromType($this->traffic_source_type)?->value,
                'reference' => $this->sourceDetail(),
            ] : null,
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

    protected function sourceDetail(): ?string
    {
        $reference = $this->traffic_source_reference;

        if (!$reference) {
            return null;
        }

        $isCampaign = TrafficSourcesTypeEnum::tryFrom($this->traffic_source_type)?->isPaid()
            || ($this->traffic_source_type === WebsiteVisitorChannelEnum::EMAIL_TYPE && !str_contains($reference, '.'));

        return $isCampaign ? __('Campaign :campaign', ['campaign' => $reference]) : $reference;
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

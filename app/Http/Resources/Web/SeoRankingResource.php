<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $keyword
 * @property string $country_code
 * @property SeoKeywordDeviceEnum $device
 * @property SeoKeywordFrequencyEnum $frequency
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property int|null $position
 * @property int|null $previous_position
 * @property \Illuminate\Support\Carbon|null $previous_checked_at
 * @property string|null $ranking_url
 * @property array|null $serp_features
 * @property bool $in_ai_overview
 * @property string|null $pending_task_id
 * @property int|null $avg_monthly_searches
 * @property string|null $intent
 * @property float|null $search_console_position
 * @property array|null $competitor_positions
 */
class SeoRankingResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'keyword'                 => $this->keyword,
            'country_code'            => $this->country_code,
            'device'                  => $this->device->value,
            'frequency'               => $this->frequency->value,
            'last_checked_at'         => $this->last_checked_at?->toDateString(),
            'is_pending'              => $this->pending_task_id !== null,
            'position'                => $this->position,
            'previous_position'       => $this->previous_position,
            'has_previous_check'      => $this->previous_checked_at !== null,
            'ranking_url'             => $this->ranking_url,
            'serp_features'           => $this->serp_features ?? [],
            'in_ai_overview'          => $this->in_ai_overview,
            'avg_monthly_searches'    => $this->avg_monthly_searches !== null ? (int) $this->avg_monthly_searches : null,
            'intent'                  => $this->intent,
            'search_console_position' => $this->search_console_position !== null ? (float) $this->search_console_position : null,
            'competitor_positions'    => $this->competitor_positions ?? [],
        ];
    }
}

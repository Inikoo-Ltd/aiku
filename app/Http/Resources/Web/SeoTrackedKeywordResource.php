<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $keyword
 * @property string $country_code
 * @property string $language_code
 * @property SeoKeywordFrequencyEnum $frequency
 * @property bool $is_active
 * @property int|null $avg_monthly_searches
 * @property string|null $target_webpage_code
 * @property array|null $tags
 * @property int $frequencies
 * @property int $languages
 * @property \Illuminate\Support\Collection|null $countries
 */
class SeoTrackedKeywordResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $countries = collect($this->countries ?? []);

        return [
            'id'                   => $this->id,
            'keyword'              => $this->keyword,
            'language_code'        => (int) $this->languages > 1 ? null : $this->language_code,
            'frequency'            => $this->frequency->value,
            'has_mixed_frequency'  => (int) $this->frequencies > 1,
            'is_active'            => $this->is_active,
            'avg_monthly_searches' => $countries->contains(fn ($country) => $country->avg_monthly_searches !== null) ? (int) $countries->sum('avg_monthly_searches') : null,
            'target_webpage_code'  => $this->target_webpage_code,
            'tags'                 => $this->tags ?? [],
            'countries'            => $countries->map(fn ($country) => [
                'id'                   => $country->id,
                'country_code'         => $country->country_code,
                'language_code'        => $country->language_code,
                'frequency'            => $country->frequency->value,
                'is_active'            => (bool) $country->is_active,
                'avg_monthly_searches' => $country->avg_monthly_searches !== null ? (int) $country->avg_monthly_searches : null,
                'update_route'         => [
                    'name'       => 'grp.models.seo_tracked_keyword.update',
                    'parameters' => [$country->id],
                    'method'     => 'patch',
                ],
                'delete_route'         => [
                    'name'       => 'grp.models.seo_tracked_keyword.delete',
                    'parameters' => [$country->id],
                    'method'     => 'delete',
                ],
            ])->values()->all(),
            'update_route'         => [
                'name'       => 'grp.models.seo_tracked_keyword.update',
                'parameters' => [$this->id],
                'method'     => 'patch',
            ],
            'delete_route'         => [
                'name'       => 'grp.models.seo_tracked_keyword.delete',
                'parameters' => [$this->id],
                'method'     => 'delete',
            ],
        ];
    }
}

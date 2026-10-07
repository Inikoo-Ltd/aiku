<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
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
 * @property string $language_code
 * @property SeoKeywordDeviceEnum $device
 * @property SeoKeywordFrequencyEnum $frequency
 * @property bool $is_active
 * @property string|null $target_webpage_code
 */
class SeoTrackedKeywordResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'keyword'              => $this->keyword,
            'country_code'         => $this->country_code,
            'language_code'        => $this->language_code,
            'device'               => $this->device->value,
            'frequency'            => $this->frequency->value,
            'is_active'            => $this->is_active,
            'target_webpage_code'  => $this->target_webpage_code,
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

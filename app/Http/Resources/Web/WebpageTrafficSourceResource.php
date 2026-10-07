<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $type
 * @property int $arrivals
 * @property float $share
 * @property int $first_arrivals
 * @property int $returning_arrivals
 * @property int $customers
 * @property string|null $last_arrival_at
 */
class WebpageTrafficSourceResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $type = TrafficSourcesTypeEnum::tryFrom($this->type);

        return [
            'type'               => $this->type,
            'label'              => TrafficSourcesTypeEnum::labels()[$this->type] ?? $this->type,
            'group'              => $type?->group()['label'] ?? __('Other'),
            'arrivals'           => (int) $this->arrivals,
            'share'              => (float) $this->share,
            'first_arrivals'     => (int) $this->first_arrivals,
            'returning_arrivals' => (int) $this->returning_arrivals,
            'customers'          => (int) $this->customers,
            'last_arrival_at'    => $this->last_arrival_at,
        ];
    }
}

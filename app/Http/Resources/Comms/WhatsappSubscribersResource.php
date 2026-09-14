<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use App\Enums\Comms\WhatsappSubscriber\WhatsappSubscriberOptInMethodEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class WhatsappSubscribersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'phone_number'  => $this->phone_number,
            'parent_type'   => $this->parent_type,
            'customer_slug' => $this->customer_slug,
            'opt_in_method' => $this->optInMethodLabel(),
            'created_at'    => $this->formatDate($this->created_at),
        ];
    }

    private function optInMethodLabel(): ?string
    {
        $optInMethod = $this->opt_in_method instanceof WhatsappSubscriberOptInMethodEnum
            ? $this->opt_in_method->value
            : $this->opt_in_method;

        if (blank($optInMethod)) {
            return null;
        }

        return WhatsappSubscriberOptInMethodEnum::labels()[$optInMethod] ?? $optInMethod;
    }

    /**
     * The joined selects return raw timestamp strings rather than Carbon instances,
     * so they are parsed here instead of being formatted straight off the model.
     */
    private function formatDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return Carbon::parse($date)->format('d F Y, H:i');
    }
}

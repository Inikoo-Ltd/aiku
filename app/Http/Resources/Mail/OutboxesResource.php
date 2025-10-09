<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Mail;

use App\Enums\Comms\Outbox\OutboxTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $data
 * @property string $name
 * @property OutboxTypeEnum $type
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property int $number_mailshots
 * @property int $dispatched_emails_lw
 * @property int $opened_emails_lw
 * @property int $unsubscribed_lw
 * @property int $runs
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * x@property mixed $shop_slug
 * @property mixed $state
 *
 */
class OutboxesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                 => $this->slug,
            'data'                 => $this->data,
            'name'                 => $this->name,
            'type'                 => $this->type->icon()[$this->type->value],
            'state'                => $this->state->icon()[$this->state->value],
            'number_mailshots'     => $this->number_mailshots,
            'dispatched_emails_lw' => $this->dispatched_emails_lw,
            'opened_emails_lw'     => $this->opened_emails_lw,
            'runs'                 => $this->runs,
            'unsubscribed_lw'      => $this->unsubscribed_lw,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'organisation_name'    => $this->organisation_name,
            'organisation_slug'    => $this->organisation_slug,
            'shop_name'            => $this->shop_name,
            'shop_slug'            => $this->shop_slug,
        ];
    }
}

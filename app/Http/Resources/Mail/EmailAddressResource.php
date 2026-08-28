<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Http\Resources\Mail;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property integer $number_outboxes
 * @property integer $number_mailshots
 * @property integer $number_dispatched_emails
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 *
 */
class EmailAddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'email'   => $this->email,
        ];
    }
}

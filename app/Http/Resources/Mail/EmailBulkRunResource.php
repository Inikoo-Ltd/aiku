<?php

/*
 * author Arya Permana - Kirin
 * created on 16-12-2024-15h-11m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Mail;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailBulkRunResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'state' => $this->state,
        ];
    }
}

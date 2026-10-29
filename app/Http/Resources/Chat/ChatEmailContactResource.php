<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Http\Resources\Chat;

use App\Actions\Chat\EmailContact\SearchChatEmailContacts;
use App\Models\Chat\ChatEmailContact;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ChatEmailContact $resource
 */
class ChatEmailContactResource extends JsonResource
{
    public function toArray($request): array
    {
        return SearchChatEmailContacts::emailContactData($this->resource);
    }
}

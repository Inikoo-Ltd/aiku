<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 23:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;

/**
 * A customer message and what our agent replied to it, from archived emails and chats, with an
 * embedding of the customer message so suggestions can be shown how we answered messages that
 * mean the same thing.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property string $source
 * @property int $source_id
 * @property string $customer_wrote
 * @property string $reply
 * @property \Illuminate\Support\Carbon $replied_at
 * @property array<int, float>|null $embedding
 */
class ChatReplyExample extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'embedding'  => 'array',
        ];
    }
}

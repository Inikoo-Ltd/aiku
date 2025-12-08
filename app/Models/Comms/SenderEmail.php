<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\SenderEmail\SenderEmailStateEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Comms\SenderEmail
 *
 * @property int $id
 * @property string $email_address
 * @property int $usage_count
 * @property SenderEmailStateEnum $state
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $last_verification_submitted_at
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SenderEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SenderEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SenderEmail query()
 *
 * @mixin \Eloquent
 */
class SenderEmail extends Model
{
    protected $casts = [
        'data' => 'array',
        'state' => SenderEmailStateEnum::class,
        'last_verification_submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];
}

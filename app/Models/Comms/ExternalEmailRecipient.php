<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 13 Feb 2026 08:49:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\InShop;

class ExternalEmailRecipient extends Model
{
    use InShop;

    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

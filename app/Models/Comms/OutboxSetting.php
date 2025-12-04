<?php

/*
 * Author:ekayudinata <ekayudinatha@gmail.com>
 * Created: Tue, 19 Dec 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, ekayudinata
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboxSetting extends Model
{
    protected $table = 'outbox_settings';

    protected $fillable = [
        'days_after',
        'send_time',
    ];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

}

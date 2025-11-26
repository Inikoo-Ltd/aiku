<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 11:47:40 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationSalesMetrics extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

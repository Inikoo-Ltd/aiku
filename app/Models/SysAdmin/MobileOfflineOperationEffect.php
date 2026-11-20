<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileOfflineOperationEffect extends Model
{
    public const null UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after'  => 'array',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(MobileOfflineOperation::class, 'mobile_offline_operation_id');
    }
}

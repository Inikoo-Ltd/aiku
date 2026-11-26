<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Traits;

use App\Events\BroadcastMobileRecordChanged;

trait BroadcastsMobileRecordChanges
{
    public static function bootBroadcastsMobileRecordChanges(): void
    {
        static::saved(fn ($model) => BroadcastMobileRecordChanged::dispatchFor($model));
        static::deleted(fn ($model) => BroadcastMobileRecordChanged::dispatchFor($model));
    }
}

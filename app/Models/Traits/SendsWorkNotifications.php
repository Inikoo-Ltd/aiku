<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Traits;

use App\Notifications\Work\WorkNotifier;

trait SendsWorkNotifications
{
    public static function bootSendsWorkNotifications(): void
    {
        static::updated(fn ($model) => WorkNotifier::recordUpdated($model));
        static::deleted(fn ($model) => WorkNotifier::recordDeleted($model));
    }
}

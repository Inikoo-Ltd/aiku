<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-14h-29m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Web;

use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class WebLayoutTemplate extends Model implements Auditable
{
    use HasFactory;
    use HasHistory;

    protected $casts = [
        'blocks'                        => 'array',
    ];

    protected $attributes = [
        'blocks'                        => 'array',
    ];

    protected $guarded = [];
}

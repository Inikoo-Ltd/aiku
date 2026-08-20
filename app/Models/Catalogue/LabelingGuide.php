<?php

/*
 * Author Louis Perez
 * Created on 20-08-2026-14h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Catalogue;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelingGuide extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uploaded_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    protected $attributes = [
    ];

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

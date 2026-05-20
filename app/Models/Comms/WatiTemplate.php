<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatiTemplate extends Model
{
    protected $fillable = [
        'shop_id',
        'waba_id',
        'element_name',
        'category',
        'sub_category',
        'status',
        'type',
        'language',
        'header',
        'body',
        'body_original',
        'footer',
        'buttons',
        'buttons_type',
        'quality',
        'creation_method',
        'last_modified',
    ];

    protected function casts(): array
    {
        return [
            'language'      => 'array',
            'header'        => 'array',
            'buttons'       => 'array',
            'last_modified' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

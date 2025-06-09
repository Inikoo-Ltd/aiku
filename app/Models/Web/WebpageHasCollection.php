<?php
/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-15h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Web;

use App\Models\Catalogue\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebpageHasCollection extends Model
{

    protected $casts = [
    ];

    protected $attributes = [
    ];

    protected $guarded = [];

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

}

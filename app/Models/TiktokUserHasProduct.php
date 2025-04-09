<?php

namespace App\Models;

use App\Models\Dropshipping\Portfolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokUserHasProduct extends Model
{
    protected $guarded = [];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}

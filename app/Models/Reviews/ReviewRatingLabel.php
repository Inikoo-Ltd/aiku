<?php

namespace App\Models\Reviews;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

class ReviewRatingLabel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'weight' => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'model_id')
            ->whereRaw('LOWER(review_rating_labels.model_type) = ?', ['group']);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'model_id')
            ->whereRaw('LOWER(review_rating_labels.model_type) = ?', ['organisation']);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'model_id')
            ->whereRaw('LOWER(review_rating_labels.model_type) = ?', ['shop']);
    }
}

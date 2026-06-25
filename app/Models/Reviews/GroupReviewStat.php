<?php

namespace App\Models\Reviews;

use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupReviewStat extends Model
{
    protected $table = 'group_review_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}

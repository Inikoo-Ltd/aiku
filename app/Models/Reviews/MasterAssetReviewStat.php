<?php

namespace App\Models\Reviews;

use App\Models\Masters\MasterAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterAssetReviewStat extends Model
{
    protected $table = 'master_asset_review_stats';

    protected $guarded = [];

    public function masterAsset(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class);
    }
}

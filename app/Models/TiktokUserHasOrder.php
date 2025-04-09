<?php

namespace App\Models;

use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TiktokUserHasOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => ChannelFulfilmentStateEnum::class
    ];

    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 23:40:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CruxRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end'   => 'date',
            'cls_p75'      => 'float',
            'histograms'   => 'array',
        ];
    }

    protected $attributes = [
        'histograms' => '{}',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }
}

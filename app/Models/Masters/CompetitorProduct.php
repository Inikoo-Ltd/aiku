<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product as a competitor sells it, read from its feed or its search results.
 *
 * @property int $id
 * @property int $competitor_id
 * @property string $code their item number, or the product link when read from their website
 * @property string|null $url
 * @property string|null $image_url
 * @property string $name
 * @property string|null $barcode
 * @property string|null $price in the competitor's currency, for the whole pack
 * @property string|null $rrp
 * @property string $units units in the pack
 * @property int|null $minimum_order
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Competitor $competitor
 */
class CompetitorProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}

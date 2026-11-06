<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 18:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many of a competitor's products were on offer each day its feed was read, so a shop that is
 * "always on sale" shows as such.
 *
 * @property int $id
 * @property int $competitor_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $number_products
 * @property int $number_on_sale
 * @property string|null $average_discount percent off the was price, over the products on sale
 * @property-read Competitor $competitor
 */
class CompetitorDailyStat extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}

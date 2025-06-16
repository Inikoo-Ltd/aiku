<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 May 2025 16:28:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $tiktok_user_id
 * @property string $productable_type
 * @property int $productable_id
 * @property string $tiktok_product_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $portfolio_id
 * @property-read \App\Models\Dropshipping\Portfolio|null $portfolio
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasProduct query()
 * @mixin \Eloquent
 */
class TiktokUserHasProduct extends Model
{
    protected $guarded = [];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}

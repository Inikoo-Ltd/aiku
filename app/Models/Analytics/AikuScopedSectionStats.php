<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Nov 2024 12:50:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $aiku_scoped_section_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuScopedSectionStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuScopedSectionStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuScopedSectionStats query()
 * @mixin \Eloquent
 */
class AikuScopedSectionStats extends Model
{
    protected $table = 'aiku_scoped_section_stats';

    protected $guarded = [
    ];


}

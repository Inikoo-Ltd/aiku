<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 10:34:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $raw_material_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterialStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterialStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterialStats query()
 * @mixin \Eloquent
 */
class RawMaterialStats extends Model
{
}

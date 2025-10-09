<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 10:34:51 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $artefact_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactStats query()
 * @mixin \Eloquent
 */
class ArtefactStats extends Model
{
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Comms\EmailAddress
 *
 * @property int $id
 * @property int $group_id
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @method static Builder<static>|EmailAddress newModelQuery()
 * @method static Builder<static>|EmailAddress newQuery()
 * @method static Builder<static>|EmailAddress query()
 * @mixin Eloquent
 */
class EmailAddress extends Model
{
    use InGroup;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'email';
    }
}

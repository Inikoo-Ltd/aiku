<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jan 2024 02:24:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\SysAdmin\UserHasAuthorisedModels
 *
 * @property int|null $org_id Not using organisation_id to avoid confusion with the organisation_id column in the users table
 * @property int|null $user_id
 * @property string $model_type
 * @property int $model_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\SysAdmin\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHasAuthorisedModels newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHasAuthorisedModels newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHasAuthorisedModels query()
 *
 * @mixin \Eloquent
 */
class UserHasAuthorisedModels extends Model
{
    protected $table = 'user_has_authorised_models';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'org_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}

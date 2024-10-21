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
 * App\Models\SysAdmin\OrganisationAuthorisedModels
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationAuthorisedModels newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationAuthorisedModels newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationAuthorisedModels query()
 * @mixin \Eloquent
 */
class OrganisationAuthorisedModels extends Model
{
    protected $table = 'user_has_authorised_models';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
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

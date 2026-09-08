<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 14:35:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\DevOps;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $github_username
 * @property string|null $avatar
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Committer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Committer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Committer query()
 * @mixin \Eloquent
 */
class Committer extends Model
{
    protected $fillable = ['name', 'email', 'github_username', 'avatar'];
}

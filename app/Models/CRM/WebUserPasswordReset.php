<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Jan 2025 20:03:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $website_id
 * @property int $web_user_id
 * @property string $token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\WebUser $webUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserPasswordReset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserPasswordReset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserPasswordReset query()
 * @mixin \Eloquent
 */
class WebUserPasswordReset extends Model
{
    protected $guarded = [];

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class);
    }

}

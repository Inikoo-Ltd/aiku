<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $created_at
 * @property string $feature
 * @property string $provider
 * @property string|null $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property string|null $cost
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsage query()
 * @mixin \Eloquent
 */
class AiUsage extends Model
{
    protected $table = 'ai_usages';

    public $timestamps = false;

    protected $guarded = [];
}

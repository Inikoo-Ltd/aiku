<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2024 14:51:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property int $dispatched_email_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasDispatchedEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasDispatchedEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasDispatchedEmail query()
 * @mixin \Eloquent
 */
class ModelHasDispatchedEmail extends Model
{
    protected $table = 'model_has_dispatched_emails';

    protected $guarded = [];

}

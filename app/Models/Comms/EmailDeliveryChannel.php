<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $model_type Mailshot, EmailBulkRun
 * @property int $model_id
 * @property int $number_emails
 * @property EmailDeliveryChannelStateEnum $state
 * @property string|null $start_sending_at
 * @property string|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailDeliveryChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailDeliveryChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailDeliveryChannel query()
 * @mixin \Eloquent
 */
class EmailDeliveryChannel extends Model
{
    protected $casts = [

        'state' => EmailDeliveryChannelStateEnum::class

    ];

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

}

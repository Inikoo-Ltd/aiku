<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 19:05:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $poll_id
 * @property int|null $poll_option_id
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property int|null $language_id
 * @property array<array-key, mixed> $translations
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read \App\Models\Helpers\Language|null $language
 * @property-read \App\Models\CRM\Poll|null $poll
 * @property-read \App\Models\CRM\PollOption|null $pollOption
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollReply newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollReply newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollReply query()
 * @mixin \Eloquent
 */
class PollReply extends Model
{
    protected $table = 'poll_replies';

    protected $casts = [
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
        'translations'       => 'array',
    ];

    protected $attributes = [
        'translations'       => '{}',
    ];

    protected $guarded = [];



    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }


    public function pollOption(): BelongsTo
    {
        return $this->belongsTo(PollOption::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Helpers\Language::class);
    }
}

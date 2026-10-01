<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 00:23:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Models\SysAdmin\User;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A webmaster's verdict on one machine translation of a catalogue text: the stars it got and
 * the text they wrote instead. Kept as training data and fed back to the translator as examples.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $language_id
 * @property string $model_type
 * @property int $model_id
 * @property string $field
 * @property string $source_text
 * @property string $machine_text
 * @property string $machine_text_hash
 * @property string|null $corrected_text
 * @property int|null $rating
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TranslationReview extends Model
{
    use InShop;

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

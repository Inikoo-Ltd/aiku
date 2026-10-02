<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 01:18:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How our webmasters translate one English product term in one language, mined from the names
 * they wrote and the machine translations they corrected.
 *
 * @property int $id
 * @property int $language_id
 * @property string $source_term
 * @property string $target_term
 * @property int $support
 * @property int $examples
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TranslationTerm extends Model
{
    protected $guarded = [];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}

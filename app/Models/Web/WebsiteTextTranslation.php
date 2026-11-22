<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A website text (web block, header, footer, menu, sidebar, webpage title) in a language other than the shop's,
 * found by the sha1 of the shop's own text exactly as stored. origin is machine or human; machine fills never
 * replace an existing row, so German a person wrote stays.
 *
 * @property int $id
 * @property int $website_id
 * @property string $locale
 * @property string $source_hash
 * @property string $source_text
 * @property string $text
 * @property string $origin
 */
class WebsiteTextTranslation extends Model
{
    public const string MACHINE = 'machine';

    public const string HUMAN = 'human';

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}

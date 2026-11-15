<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\BankPayee\BankPayeeCategoryEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Who a payee of the group's banks is: every outgoing bank line paid to this name carries its category.
 *
 * @property int $id
 * @property int $group_id
 * @property string $key the payee's name in lower case, without punctuation or repeated spaces
 * @property string $name
 * @property BankPayeeCategoryEnum $category
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string $source auto when guessed from a name in Aiku, person when someone chose it
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|null $model
 * @mixin \Eloquent
 */
class BankPayee extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'category' => BankPayeeCategoryEnum::class,
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public static function normalise(?string $name): ?string
    {
        $key = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $name)));
        $key = preg_replace('/\blimited\b/u', 'ltd', $key);

        return $key === '' ? null : $key;
    }
}

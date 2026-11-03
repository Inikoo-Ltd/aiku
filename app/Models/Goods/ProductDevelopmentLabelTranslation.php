<?php

/*
 * Author Louis Perez
 * Created on 03-11-2026-13h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A machine translation of a SKU's label text into one language, kept to copy onto the artwork. It is
 * AI-generated and never approved; it goes out of date when the English label text changes.
 *
 * @property int $id
 * @property int $product_development_item_id
 * @property int $language_id
 * @property string $text
 * @property string $source_hash fingerprint of the English label text it was translated from
 * @property int|null $translated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductDevelopmentItem|null $item
 * @property-read Language|null $language
 * @property-read User|null $translatedBy
 * @mixin \Eloquent
 */
class ProductDevelopmentLabelTranslation extends Model
{
    protected $table = 'product_development_label_translations';

    protected $guarded = [];

    public static function sourceHash(string $labelText): string
    {
        return hash('sha256', $labelText);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentItem::class, 'product_development_item_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function translatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translated_by_id');
    }
}

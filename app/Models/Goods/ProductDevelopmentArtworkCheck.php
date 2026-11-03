<?php

/*
 * Author Louis Perez
 * Created on 03-11-2026-13h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The AI pre-check of one artwork version against the SKU's approved label content. It only advises the
 * reviewer: it never approves, blocks or changes the artwork.
 *
 * @property int $id
 * @property int $artwork_id
 * @property int $version
 * @property string $state checking, done or failed
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property string|null $model
 * @property int|null $requested_by_id
 * @property Carbon|null $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductDevelopmentArtwork|null $artwork
 * @property-read User|null $requestedBy
 * @mixin \Eloquent
 */
class ProductDevelopmentArtworkCheck extends Model
{
    public const string CHECKING = 'checking';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    protected $table = 'product_development_artwork_checks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result'     => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentArtwork::class, 'artwork_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}

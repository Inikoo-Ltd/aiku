<?php

/*
 * author Louis Perez
 * created on 10-02-2026-11h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\Web;

use App\Enums\Web\WebLayoutTemplate\WebLayoutTemplateType;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 
 * @mixin \Eloquent
 */
class WebLayoutTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasHistory;

    protected $casts = [
        'data'          => 'object',
        'type'          => WebLayoutTemplateType::class,
    ];

    protected $attributes = [
        'data'   => '{}',
    ];

    protected $guarded = [];

}

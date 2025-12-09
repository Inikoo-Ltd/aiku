<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/**
 * @property int $id
 * @property string $guid
 * @property string $sort_order
 * @property string|null $content
 * @property array<array-key, mixed>|null $metadata
 * @property \Pgvector\Laravel\Vector|null $embedding_768
 * @property \Pgvector\Laravel\Vector|null $embedding_1536
 * @property \Pgvector\Laravel\Vector|null $embedding_2048
 * @property \Pgvector\Laravel\Vector|null $embedding_3072
 * @property \Pgvector\Laravel\Vector|null $embedding_1024
 * @property \Pgvector\Laravel\Vector|null $embedding_4096
 * @property int|null $section_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chunk nearestNeighbors(string $column, ?mixed $value, \Pgvector\Laravel\Distance $distance)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chunk newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chunk newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chunk query()
 * @mixin \Eloquent
 */
class Chunk extends Model
{
    use HasFactory;
    use HasNeighbors;

    protected $guarded = [];

    protected $casts = [
        'embedding_3072' => Vector::class,
        'embedding_1536' => Vector::class,
        'embedding_2048' => Vector::class,
        'embedding_1024' => Vector::class,
        'embedding_4096' => Vector::class,
        'embedding_768' => Vector::class,
        'metadata' => 'array',
    ];
}

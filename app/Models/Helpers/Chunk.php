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
 * @property \Pgvector\Laravel\Vector $embedding_3072
 * @property \Pgvector\Laravel\Vector $embedding_1536
 * @property \Pgvector\Laravel\Vector $embedding_2048
 * @property \Pgvector\Laravel\Vector $embedding_1024
 * @property \Pgvector\Laravel\Vector $embedding_4096
 * @property \Pgvector\Laravel\Vector $embedding_768
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

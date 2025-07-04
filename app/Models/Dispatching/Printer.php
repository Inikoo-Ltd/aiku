<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Dispatching;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Dispatching\Printer
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array|null $capabilities
 * @property array|null $trays
 * @property string $status
 * @property bool $is_online
 * @property int|null $computer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Computer|null $computer
 * @method static \Illuminate\Database\Eloquent\Builder|Printer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Printer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Printer query()
 * @mixin \Eloquent
 */
class Printer extends Model
{
    use HasFactory;

    protected $table = 'printers';

    protected $casts = [
        'capabilities' => 'array',
        'trays'        => 'array',
        'is_online'    => 'boolean',
    ];

    protected $guarded = [];

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class, 'computer_id');
    }
}

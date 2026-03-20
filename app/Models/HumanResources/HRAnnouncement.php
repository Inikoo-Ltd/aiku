<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HRAnnouncement extends Model
{
    protected $table = 'hr_announcements';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

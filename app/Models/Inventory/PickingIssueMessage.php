<?php

/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-18h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Inventory;

use App\Enums\Dispatching\PickingIssueMessage\PickingIssueMessageTypeEnum;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PickingIssueMessage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'type'  => PickingIssueMessageTypeEnum::class,
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function pickingIssue(): BelongsTo
    {
        return $this->belongsTo(PickingIssue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}

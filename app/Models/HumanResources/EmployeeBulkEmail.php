<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $sender_id
 * @property string $subject
 * @property string $body
 * @property int $number_recipients
 * @property int $number_pending
 * @property array<int, array{path: string, name: string}> $attachments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read User|null $sender
 * @mixin \Eloquent
 */
class EmployeeBulkEmail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
